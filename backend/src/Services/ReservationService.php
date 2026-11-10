<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Helpers\Clock;
use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Helpers\RequestContext;
use App\Repositories\BookRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Repositories\StudentRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;

final class ReservationService
{
    public const MAX_ACTIVE_PER_STUDENT = 3;

    public function __construct(
        private readonly Database $db,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly BookRepositoryInterface $books,
        private readonly StudentRepositoryInterface $students,
        private readonly IssueRepositoryInterface $issues,
        private readonly ReservationQueue $queue,
        private readonly CirculationService $circulation,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
        private readonly RequestContext $context,
        private readonly Clock $clock,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->reservations->paginate($query);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function reserve(array $input): array
    {
        $studentId = $this->context->user()->studentId ?? throw new AuthorizationException('Only students can reserve books');
        $bookId = (int) Validator::validate($input, Rules::reservation())['book_id'];

        $id = $this->db->transaction(function () use ($studentId, $bookId): int {
            $student = $this->students->findById($studentId, true) ?? throw new NotFoundException('Student not found');
            if ($student->isSuspended()) {
                throw new ConflictException('Your account is suspended', 'ACCOUNT_SUSPENDED');
            }
            $this->books->findById($bookId, true) ?? throw new NotFoundException('Book not found');
            if ($this->reservations->findActive($studentId, $bookId) !== null) {
                throw new ConflictException('You already have an active reservation for this book', 'ALREADY_RESERVED');
            }
            if ($this->issues->studentHasActiveIssueOfBook($studentId, $bookId)) {
                throw new ConflictException('You already have this book on loan', 'ALREADY_BORROWED');
            }
            if ($this->reservations->countActiveForStudent($studentId) >= self::MAX_ACTIVE_PER_STUDENT) {
                throw new ConflictException('You can have at most ' . self::MAX_ACTIVE_PER_STUDENT . ' active reservations', 'MAX_RESERVATIONS');
            }
            $id = $this->reservations->create($bookId, $studentId);
            $this->queue->promote($bookId);

            return $id;
        });

        return ($this->reservations->findById($id) ?? throw new NotFoundException())->toArray();
    }

    public function cancel(int $id): void
    {
        $actor = $this->context->user();
        $this->db->transaction(function () use ($id, $actor): void {
            $reservation = $this->reservations->findById($id, true);
            if ($reservation === null || (!$actor->isStaff() && $reservation->studentId !== $actor->studentId)) {
                throw new NotFoundException('Reservation not found');
            }
            if (!$reservation->isActive()) {
                throw new ConflictException('This reservation is no longer active', 'NOT_ACTIVE');
            }
            $this->reservations->updateStatus($reservation->id, 'cancelled');
            if ($reservation->status === 'ready') {
                $this->queue->promote($reservation->bookId);
            }
            if ($actor->isStaff()) {
                $this->notifications->notify(
                    $reservation->studentUserId,
                    'Reservation cancelled',
                    sprintf('Your reservation for "%s" was cancelled by the library.', $reservation->bookTitle)
                );
                $this->audit->log('reservation.cancel', 'reservation', $reservation->id);
            }
        });
    }

    /**
     * Issues an available copy to the reserving student.
     *
     * @return Row the created issue
     */
    public function fulfil(int $id): array
    {
        $reservation = $this->reservations->findById($id) ?? throw new NotFoundException('Reservation not found');
        if (!$reservation->isActive()) {
            throw new ConflictException('This reservation is no longer active', 'NOT_ACTIVE');
        }
        $copy = $this->books->firstAvailableCopy($reservation->bookId)
            ?? throw new ConflictException('No copy of this book is currently available', 'NO_COPY_AVAILABLE');

        return $this->circulation->issueCopy($reservation->studentId, $copy->barcode);
    }

    /** Cron: expires uncollected holds and offers the copy to the next student in the queue. */
    public function expireStale(): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        return $this->db->transaction(function () use ($now): int {
            $expired = $this->reservations->findExpired($now);
            foreach ($expired as $reservation) {
                $this->reservations->updateStatus($reservation->id, 'expired', $reservation->expiresAt);
                $this->notifications->notify(
                    $reservation->studentUserId,
                    'Reservation expired',
                    sprintf('Your hold on "%s" expired because it was not collected in time.', $reservation->bookTitle)
                );
            }
            foreach (array_unique(array_map(static fn ($r): int => $r->bookId, $expired)) as $bookId) {
                $this->queue->promote($bookId);
            }

            return count($expired);
        });
    }
}
