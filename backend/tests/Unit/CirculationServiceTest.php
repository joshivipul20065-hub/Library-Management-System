<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ConflictException;
use App\Helpers\Clock;
use App\Helpers\Database;
use App\Helpers\RequestContext;
use App\Models\AuthUser;
use App\Models\BookCopy;
use App\Models\Issue;
use App\Models\Student;
use App\Repositories\BookRepositoryInterface;
use App\Repositories\FineRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Repositories\StudentRepositoryInterface;
use App\Services\AuditService;
use App\Services\CirculationService;
use App\Services\FineCalculator;
use App\Services\NotificationService;
use App\Services\ReservationQueue;
use App\Services\SettingsService;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Business rules of issue / renew / return, with repositories replaced by test doubles. */
final class CirculationServiceTest extends TestCase
{
    private IssueRepositoryInterface&MockObject $issues;
    private BookRepositoryInterface&MockObject $books;
    private StudentRepositoryInterface&MockObject $students;
    private FineRepositoryInterface&MockObject $fines;
    private ReservationRepositoryInterface&MockObject $reservations;
    private CirculationService $service;

    protected function setUp(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);

        $this->issues = $this->createMock(IssueRepositoryInterface::class);
        $this->books = $this->createMock(BookRepositoryInterface::class);
        $this->students = $this->createMock(StudentRepositoryInterface::class);
        $this->fines = $this->createMock(FineRepositoryInterface::class);
        $this->reservations = $this->createMock(ReservationRepositoryInterface::class);

        $settings = $this->createMock(SettingsService::class);
        $settings->method('int')->willReturnMap([
            ['loan_days', 14],
            ['max_books_per_student', 5],
            ['max_renewals', 3],
        ]);
        $settings->method('float')->willReturnMap([
            ['fine_per_day', 5.0],
            ['lost_book_fee', 500.0],
        ]);

        $context = new RequestContext();
        $context->user = new AuthUser(1, AuthUser::ROLE_LIBRARIAN, 'Librarian', 'lib@test', 'active', null);

        $this->issues->method('details')->willReturn(['id' => 99]);
        $this->books->method('findById')->willReturn(['id' => 7, 'available_copies' => 1]);

        $this->service = new CirculationService(
            new Database($pdo),
            $this->issues,
            $this->books,
            $this->students,
            $this->fines,
            $this->reservations,
            $this->createMock(ReservationQueue::class),
            $settings,
            new FineCalculator(),
            $this->createMock(NotificationService::class),
            $this->createMock(AuditService::class),
            $context,
            new Clock('2026-03-01 10:00:00'),
        );
    }

    public function testIssuesAvailableCopyWithDefaultLoanPeriod(): void
    {
        $this->givenStudent();
        $this->givenCopy('available');
        $this->issues->expects(self::once())->method('create')
            ->with(30, 5, 1, '2026-03-01', '2026-03-15')
            ->willReturn(99);
        $this->books->expects(self::once())->method('setCopyStatus')->with(30, 'issued');
        $this->books->expects(self::once())->method('adjustCopyCounts')->with(7, 0, -1);

        self::assertSame(['id' => 99], $this->service->issueCopy(5, 'LIB-1'));
    }

    public function testRejectsSuspendedStudent(): void
    {
        $this->givenStudent(status: 'suspended');

        $this->assertConflict('ACCOUNT_SUSPENDED', fn () => $this->service->issueCopy(5, 'LIB-1'));
    }

    public function testRejectsStudentWithUnpaidFines(): void
    {
        $this->givenStudent();
        $this->fines->method('unpaidTotalForStudent')->willReturn(25.0);

        $this->assertConflict('UNPAID_FINES', fn () => $this->service->issueCopy(5, 'LIB-1'));
    }

    public function testRejectsStudentAtBorrowingLimit(): void
    {
        $this->givenStudent(maxBooks: 2);
        $this->issues->method('countActiveForStudent')->willReturn(2);

        $this->assertConflict('MAX_BOOKS_REACHED', fn () => $this->service->issueCopy(5, 'LIB-1'));
    }

    public function testRejectsCopyThatIsAlreadyIssued(): void
    {
        $this->givenStudent();
        $this->givenCopy('issued');
        $this->issues->expects(self::never())->method('create');

        $this->assertConflict('COPY_UNAVAILABLE', fn () => $this->service->issueCopy(5, 'LIB-1'));
    }

    public function testRejectsWhenLastCopyIsHeldForAnotherStudent(): void
    {
        $this->givenStudent();
        $this->givenCopy('available');
        $this->reservations->method('countReady')->with(7, 5)->willReturn(1);

        $this->assertConflict('RESERVED_FOR_OTHERS', fn () => $this->service->issueCopy(5, 'LIB-1'));
    }

    public function testRenewalBlockedAfterMaximumRenewals(): void
    {
        $this->givenIssue(dueDate: '2026-03-05', renewCount: 3);

        $this->assertConflict('MAX_RENEWALS', fn () => $this->service->renew(99));
    }

    public function testRenewalBlockedWhenOverdue(): void
    {
        $this->givenIssue(dueDate: '2026-02-20');

        $this->assertConflict('OVERDUE', fn () => $this->service->renew(99));
    }

    public function testRenewalBlockedWhenReservedByAnotherStudent(): void
    {
        $this->givenIssue(dueDate: '2026-03-05');
        $this->reservations->method('hasActiveForOthers')->with(7, 5)->willReturn(true);

        $this->assertConflict('RESERVED_FOR_OTHERS', fn () => $this->service->renew(99));
    }

    public function testRenewalExtendsDueDateFromToday(): void
    {
        $this->givenIssue(dueDate: '2026-03-05', renewCount: 1);
        $this->issues->expects(self::once())->method('renew')->with(99, '2026-03-15');

        $this->service->renew(99);
    }

    public function testLateReturnCreatesFine(): void
    {
        $this->givenIssue(dueDate: '2026-02-26');
        $this->fines->expects(self::once())->method('create')->with(99, 15.0, 'Overdue by 3 day(s)')->willReturn(1);
        $this->books->expects(self::once())->method('adjustCopyCounts')->with(7, 0, 1);

        $result = $this->service->returnIssue(99);

        self::assertSame(3, $result['days_overdue']);
        self::assertSame(15.0, $result['fine_amount']);
    }

    public function testOnTimeReturnCreatesNoFine(): void
    {
        $this->givenIssue(dueDate: '2026-03-01');
        $this->fines->expects(self::never())->method('create');

        self::assertSame(0.0, $this->service->returnIssue(99)['fine_amount']);
    }

    private function givenStudent(string $status = 'active', ?int $maxBooks = null): void
    {
        $student = new Student(5, 50, 'Asha', 'asha@test', $status, 'R-1', 'CS', 1, null, null, $maxBooks, '2026-01-01');
        $this->students->method('findById')->willReturn($student);
    }

    private function givenCopy(string $status): void
    {
        $this->books->method('findCopyByBarcode')->willReturn(new BookCopy(30, 7, 'LIB-1', $status, 'Clean Code'));
    }

    private function givenIssue(string $dueDate, int $renewCount = 0): void
    {
        $this->issues->method('findById')->willReturn(
            new Issue(99, 30, 5, 7, 50, 'Clean Code', '2026-02-12', $dueDate, null, 'issued', $renewCount)
        );
    }

    private function assertConflict(string $code, callable $action): void
    {
        try {
            $action();
            self::fail("Expected a {$code} conflict");
        } catch (ConflictException $e) {
            self::assertSame($code, $e->errorCode);
            self::assertSame(409, $e->status);
        }
    }
}
