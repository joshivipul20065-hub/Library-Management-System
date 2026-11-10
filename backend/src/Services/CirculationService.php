<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\Clock;
use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Helpers\RequestContext;
use App\Models\Issue;
use App\Models\Student;
use App\Repositories\BookRepositoryInterface;
use App\Repositories\FineRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Repositories\StudentRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;
use DateTimeImmutable;

/**
 * Issue / return / renew / lost workflows. Every workflow runs in one transaction and locks rows in a fixed
 * order (student -> issue -> copy -> book) so concurrent requests cannot double-issue a copy or deadlock.
 */
final class CirculationService
{
    public function __construct(
        private readonly Database $db,
        private readonly IssueRepositoryInterface $issues,
        private readonly BookRepositoryInterface $books,
        private readonly StudentRepositoryInterface $students,
        private readonly FineRepositoryInterface $fines,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly ReservationQueue $queue,
        private readonly SettingsService $settings,
        private readonly FineCalculator $calculator,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
        private readonly RequestContext $context,
        private readonly Clock $clock,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->issues->paginate($query);
    }

    /** @return Row */
    public function details(int $issueId): array
    {
        return $this->issues->details($issueId) ?? throw new NotFoundException('Issue not found');
    }

    /**
     * Borrowing eligibility for the issue desk.
     *
     * @return array{can_borrow: bool, reasons: list<string>, active_issues: int, limit: int, unpaid_fines: float}
     */
    public function eligibility(Student $student): array
    {
        $reasons = [];
        if ($student->isSuspended()) {
            $reasons[] = 'Account is suspended';
        }
        $unpaid = $this->fines->unpaidTotalForStudent($student->id);
        if ($unpaid > 0) {
            $reasons[] = sprintf('Unpaid fines of %.2f', $unpaid);
        }
        $limit = $student->maxBooks ?? $this->settings->int('max_books_per_student');
        $active = $this->issues->countActiveForStudent($student->id);
        if ($active >= $limit) {
            $reasons[] = "Borrowing limit of {$limit} books reached";
        }

        return ['can_borrow' => $reasons === [], 'reasons' => $reasons, 'active_issues' => $active, 'limit' => $limit, 'unpaid_fines' => $unpaid];
    }

    /**
     * @param Row $input student_id or roll_no, barcode, optional due_date
     * @return Row
     */
    public function issue(array $input): array
    {
        $data = Validator::validate($input, Rules::issue());
        if ($data['student_id'] !== null) {
            $studentId = (int) $data['student_id'];
        } elseif ($data['roll_no'] !== null) {
            $student = $this->students->findByRollNo((string) $data['roll_no'])
                ?? throw new NotFoundException('No student with this roll number', 'STUDENT_NOT_FOUND');
            $studentId = $student->id;
        } else {
            throw new ValidationException('The given data was invalid', ['roll_no' => 'Student roll number is required']);
        }

        return $this->issueCopy($studentId, (string) $data['barcode'], $data['due_date'] === null ? null : (string) $data['due_date']);
    }

    /** @return Row */
    public function issueCopy(int $studentId, string $barcode, ?string $dueDate = null): array
    {
        $issueId = $this->db->transaction(function () use ($studentId, $barcode, $dueDate): int {
            $student = $this->students->findById($studentId, true) ?? throw new NotFoundException('Student not found', 'STUDENT_NOT_FOUND');
            $eligibility = $this->eligibility($student);
            if (!$eligibility['can_borrow']) {
                $code = match (true) {
                    $student->isSuspended() => 'ACCOUNT_SUSPENDED',
                    $eligibility['unpaid_fines'] > 0 => 'UNPAID_FINES',
                    default => 'MAX_BOOKS_REACHED',
                };
                throw new ConflictException('Cannot issue: ' . implode('; ', $eligibility['reasons']), $code, $eligibility);
            }

            $copy = $this->books->findCopyByBarcode($barcode, true) ?? throw new NotFoundException('No book copy found with this barcode', 'COPY_NOT_FOUND');
            if ($copy->status !== 'available') {
                throw new ConflictException("This copy is not available (currently {$copy->status})", 'COPY_UNAVAILABLE');
            }
            if ($this->issues->studentHasActiveIssueOfBook($student->id, $copy->bookId)) {
                throw new ConflictException('The student already has a copy of this book', 'ALREADY_BORROWED');
            }
            $book = $this->books->findById($copy->bookId, true) ?? throw new NotFoundException('Book not found');
            if ($this->reservations->countReady($copy->bookId, $student->id) >= (int) $book['available_copies']) {
                throw new ConflictException('All available copies of this book are on hold for other students', 'RESERVED_FOR_OTHERS');
            }

            $today = $this->clock->today();
            $due = $dueDate === null
                ? $today->modify('+' . $this->settings->int('loan_days') . ' days')
                : new DateTimeImmutable($dueDate);
            if ($due <= $today) {
                throw new ValidationException('Due date must be in the future', ['due_date' => 'Due date must be after today']);
            }

            $id = $this->issues->create($copy->id, $student->id, $this->context->user?->id, $today->format('Y-m-d'), $due->format('Y-m-d'));
            $this->books->setCopyStatus($copy->id, 'issued');
            $this->books->adjustCopyCounts($copy->bookId, 0, -1);
            $reservation = $this->reservations->findActive($student->id, $copy->bookId);
            if ($reservation !== null) {
                $this->reservations->updateStatus($reservation->id, 'fulfilled');
            }

            $this->notifications->notify(
                $student->userId,
                'Book issued',
                sprintf('You borrowed "%s". Please return it by %s.', $copy->bookTitle, $due->format('Y-m-d'))
            );
            $this->audit->log('issue.create', 'issue', $id, ['barcode' => $barcode, 'student_id' => $student->id, 'due_date' => $due->format('Y-m-d')]);

            return $id;
        });

        return $this->details($issueId);
    }

    /** @return array{issue: Row, days_overdue: int, fine_amount: float, fine_id: int|null, reservation_ready: bool} */
    public function returnIssue(int $issueId): array
    {
        $result = $this->db->transaction(function () use ($issueId): array {
            $issue = $this->lockActiveIssue($issueId);
            $this->books->findCopy($issue->copyId, true);
            $today = $this->clock->todayString();

            $this->issues->markReturned($issue->id, $today);
            $this->books->setCopyStatus($issue->copyId, 'available');
            $this->books->adjustCopyCounts($issue->bookId, 0, 1);

            $days = $this->calculator->daysOverdue($issue->dueDate, $today);
            $amount = $this->calculator->overdueFine($issue->dueDate, $today, $this->settings->float('fine_per_day'));
            $fineId = $amount > 0 ? $this->fines->create($issue->id, $amount, sprintf('Overdue by %d day(s)', $days)) : null;
            $promoted = $this->queue->promote($issue->bookId);

            $body = sprintf('Thank you for returning "%s".', $issue->bookTitle)
                . ($fineId !== null ? sprintf(' It was %d day(s) late, so a fine of %.2f has been added to your account.', $days, $amount) : '');
            $this->notifications->notify($issue->studentUserId, 'Book returned', $body, $fineId !== null);
            $this->audit->log('issue.return', 'issue', $issue->id, ['days_overdue' => $days, 'fine' => $amount]);

            return ['days_overdue' => $days, 'fine_amount' => $amount, 'fine_id' => $fineId, 'reservation_ready' => $promoted !== []];
        });

        return ['issue' => $this->details($issueId)] + $result;
    }

    /** @return Row */
    public function renew(int $issueId): array
    {
        $this->db->transaction(function () use ($issueId): void {
            $actor = $this->context->user();
            $issue = $this->issues->findById($issueId, true);
            if ($issue === null || (!$actor->isStaff() && $issue->studentId !== $actor->studentId)) {
                throw new NotFoundException('Issue not found');
            }
            if (!$issue->isActive()) {
                throw new ConflictException('This loan is no longer active', 'NOT_ACTIVE');
            }
            $today = $this->clock->today();
            if ($issue->dueDate < $today->format('Y-m-d')) {
                throw new ConflictException('Overdue books cannot be renewed. Please return the book first.', 'OVERDUE');
            }
            $maxRenewals = $this->settings->int('max_renewals');
            if ($issue->renewCount >= $maxRenewals) {
                throw new ConflictException("Maximum number of renewals ({$maxRenewals}) reached", 'MAX_RENEWALS');
            }
            if ($this->reservations->hasActiveForOthers($issue->bookId, $issue->studentId)) {
                throw new ConflictException('Another student has reserved this book, so it cannot be renewed', 'RESERVED_FOR_OTHERS');
            }

            $newDue = $today->modify('+' . $this->settings->int('loan_days') . ' days')->format('Y-m-d');
            $this->issues->renew($issue->id, $newDue);
            $this->notifications->notify($issue->studentUserId, 'Loan renewed', sprintf('"%s" is now due on %s.', $issue->bookTitle, $newDue));
            if ($actor->isStaff()) {
                $this->audit->log('issue.renew', 'issue', $issue->id, ['due_date' => $newDue]);
            }
        });

        return $this->details($issueId);
    }

    /** @return array{issue: Row, fine_amount: float} */
    public function markLost(int $issueId): array
    {
        $amount = $this->db->transaction(function () use ($issueId): float {
            $issue = $this->lockActiveIssue($issueId);
            $this->books->findCopy($issue->copyId, true);
            $today = $this->clock->todayString();

            $this->issues->markLost($issue->id, $today);
            $this->books->setCopyStatus($issue->copyId, 'lost');
            $this->books->adjustCopyCounts($issue->bookId, -1, 0);

            $days = $this->calculator->daysOverdue($issue->dueDate, $today);
            $overdue = $this->calculator->overdueFine($issue->dueDate, $today, $this->settings->float('fine_per_day'));
            $amount = round($this->settings->float('lost_book_fee') + $overdue, 2);
            if ($amount > 0) {
                $reason = 'Lost book replacement fee' . ($days > 0 ? sprintf(' + overdue by %d day(s)', $days) : '');
                $this->fines->create($issue->id, $amount, $reason);
            }
            $this->notifications->notify(
                $issue->studentUserId,
                'Book marked as lost',
                sprintf('"%s" was marked as lost. A charge of %.2f has been added to your account.', $issue->bookTitle, $amount),
                true
            );
            $this->audit->log('issue.mark_lost', 'issue', $issue->id, ['fine' => $amount]);

            return $amount;
        });

        return ['issue' => $this->details($issueId), 'fine_amount' => $amount];
    }

    /** @return array{issue_id: int, due_date: string, days_overdue: int, fine_per_day: float, amount: float} */
    public function finePreview(int $issueId): array
    {
        $issue = $this->issues->findById($issueId) ?? throw new NotFoundException('Issue not found');
        $today = $this->clock->todayString();
        $perDay = $this->settings->float('fine_per_day');

        return [
            'issue_id' => $issue->id,
            'due_date' => $issue->dueDate,
            'days_overdue' => $issue->isActive() ? $this->calculator->daysOverdue($issue->dueDate, $today) : 0,
            'fine_per_day' => $perDay,
            'amount' => $issue->isActive() ? $this->calculator->overdueFine($issue->dueDate, $today, $perDay) : 0.0,
        ];
    }

    public function remind(int $issueId): void
    {
        $issue = $this->issues->findById($issueId) ?? throw new NotFoundException('Issue not found');
        if (!$issue->isActive()) {
            throw new ConflictException('This loan is no longer active', 'NOT_ACTIVE');
        }
        $preview = $this->finePreview($issueId);
        $body = $preview['days_overdue'] > 0
            ? sprintf(
                '"%s" was due on %s and is %d day(s) overdue. The fine so far is %.2f and grows by %.2f per day. Please return it as soon as possible.',
                $issue->bookTitle,
                $issue->dueDate,
                $preview['days_overdue'],
                $preview['amount'],
                $preview['fine_per_day']
            )
            : sprintf('Reminder: "%s" is due on %s.', $issue->bookTitle, $issue->dueDate);
        $this->notifications->notify($issue->studentUserId, 'Library reminder', $body, true);
        $this->audit->log('issue.remind', 'issue', $issue->id);
    }

    /** Cron: flags overdue loans and notifies borrowers. Returns the number of newly overdue loans. */
    public function processOverdue(): int
    {
        $today = $this->clock->todayString();
        $flagged = $this->db->transaction(fn (): array => $this->issues->flagOverdue($today));
        foreach ($flagged as $row) {
            $this->notifications->notify(
                (int) $row['student_user_id'],
                'Book overdue',
                sprintf(
                    '"%s" was due on %s. A fine of %.2f per day applies until it is returned.',
                    $row['book_title'],
                    $row['due_date'],
                    $this->settings->float('fine_per_day')
                ),
                true
            );
        }

        return count($flagged);
    }

    private function lockActiveIssue(int $issueId): Issue
    {
        $issue = $this->issues->findById($issueId, true) ?? throw new NotFoundException('Issue not found');
        if (!$issue->isActive()) {
            throw new ConflictException('This loan is no longer active', 'NOT_ACTIVE');
        }

        return $issue;
    }
}
