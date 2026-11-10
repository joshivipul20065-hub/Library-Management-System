<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\ConflictException;
use App\Services\CirculationService;
use App\Services\FineService;
use App\Services\ReservationService;
use App\Services\SettingsService;

final class CirculationFlowTest extends IntegrationTestCase
{
    public function testIssueAndOnTimeReturn(): void
    {
        $student = $this->createStudent('R-1');
        [$bookId, [$barcode]] = $this->createBook('A');

        $issue = $this->app()->get(CirculationService::class)->issueCopy($student, $barcode);
        self::assertSame('2026-03-15', $issue['due_date']);
        self::assertSame(0, $this->availableCopies($bookId));
        self::assertSame('issued', self::$db->scalar("SELECT status FROM book_copies WHERE barcode = 'A-1'"));

        $result = $this->app('2026-03-15')->get(CirculationService::class)->returnIssue((int) $issue['id']);

        self::assertSame(0.0, $result['fine_amount']);
        self::assertSame('returned', $result['issue']['status']);
        self::assertSame(1, $this->availableCopies($bookId));
        self::assertSame(0, (int) self::$db->scalar('SELECT COUNT(*) FROM fines'));
        self::assertGreaterThan(0, (int) self::$db->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'issue.return'"));
    }

    public function testLateReturnCreatesFineThatBlocksBorrowingUntilPaid(): void
    {
        $student = $this->createStudent('R-1');
        [, [$first]] = $this->createBook('A');
        [, [$second]] = $this->createBook('B');

        $issue = $this->app()->get(CirculationService::class)->issueCopy($student, $first);
        $result = $this->app('2026-03-20')->get(CirculationService::class)->returnIssue((int) $issue['id']);

        self::assertSame(5, $result['days_overdue']);
        self::assertSame(25.0, $result['fine_amount']);
        $fine = self::$db->one('SELECT * FROM fines WHERE issue_id = :id', ['id' => $issue['id']]);
        self::assertNotNull($fine);
        self::assertSame('25.00', $fine['amount']);
        self::assertSame('unpaid', $fine['status']);

        $this->assertConflict('UNPAID_FINES', fn () => $this->app('2026-03-20')->get(CirculationService::class)->issueCopy($student, $second));

        $this->app('2026-03-20')->get(FineService::class)->pay((int) $fine['id']);
        $issued = $this->app('2026-03-20')->get(CirculationService::class)->issueCopy($student, $second);
        self::assertSame('issued', $issued['status']);
    }

    public function testBorrowingLimitAndUnavailableCopy(): void
    {
        $this->app()->get(SettingsService::class)->update(['max_books_per_student' => 1]);
        $alice = $this->createStudent('R-1');
        $bob = $this->createStudent('R-2');
        [, [$a1]] = $this->createBook('A');
        [, [$b1]] = $this->createBook('B');

        $this->app()->get(CirculationService::class)->issueCopy($alice, $a1);

        $this->assertConflict('MAX_BOOKS_REACHED', fn () => $this->app()->get(CirculationService::class)->issueCopy($alice, $b1));
        $this->assertConflict('COPY_UNAVAILABLE', fn () => $this->app()->get(CirculationService::class)->issueCopy($bob, $a1));
    }

    public function testReturnedCopyIsHeldForTheNextReservation(): void
    {
        $alice = $this->createStudent('R-1');
        $bob = $this->createStudent('R-2');
        $carol = $this->createStudent('R-3');
        [$bookId, [$barcode]] = $this->createBook('A');

        $issue = $this->app()->get(CirculationService::class)->issueCopy($alice, $barcode);
        $reservation = $this->app('2026-03-02', $this->studentActor($bob))->get(ReservationService::class)->reserve(['book_id' => $bookId]);
        self::assertSame('pending', $reservation['status']);

        // Bob's reservation blocks Alice from renewing.
        $this->assertConflict(
            'RESERVED_FOR_OTHERS',
            fn () => $this->app('2026-03-03', $this->studentActor($alice))->get(CirculationService::class)->renew((int) $issue['id'])
        );

        $result = $this->app('2026-03-05')->get(CirculationService::class)->returnIssue((int) $issue['id']);
        self::assertTrue($result['reservation_ready']);
        $row = self::$db->one('SELECT status, expires_at FROM reservations WHERE id = :id', ['id' => $reservation['id']]);
        self::assertSame(['status' => 'ready', 'expires_at' => '2026-03-07 10:00:00'], $row);
        self::assertSame(1, (int) self::$db->scalar(
            "SELECT COUNT(*) FROM notifications n JOIN students s ON s.user_id = n.user_id WHERE s.id = :id AND n.title = 'Reserved book ready for pickup'",
            ['id' => $bob]
        ));

        // The held copy cannot go to someone else...
        $this->assertConflict('RESERVED_FOR_OTHERS', fn () => $this->app('2026-03-05')->get(CirculationService::class)->issueCopy($carol, $barcode));
        // ...but fulfils Bob's reservation.
        $this->app('2026-03-06')->get(ReservationService::class)->fulfil((int) $reservation['id']);
        self::assertSame('fulfilled', self::$db->scalar('SELECT status FROM reservations WHERE id = :id', ['id' => $reservation['id']]));
    }

    public function testUncollectedHoldExpiresAndPassesToNextStudent(): void
    {
        $bob = $this->createStudent('R-2');
        $carol = $this->createStudent('R-3');
        [$bookId] = $this->createBook('A');

        $first = $this->app('2026-03-01', $this->studentActor($bob))->get(ReservationService::class)->reserve(['book_id' => $bookId]);
        $second = $this->app('2026-03-01', $this->studentActor($carol))->get(ReservationService::class)->reserve(['book_id' => $bookId]);
        self::assertSame('ready', $first['status'], 'A copy is on the shelf, so the first reservation is ready immediately');
        self::assertSame('pending', $second['status']);

        $expired = $this->app('2026-03-04')->get(ReservationService::class)->expireStale();

        self::assertSame(1, $expired);
        self::assertSame('expired', self::$db->scalar('SELECT status FROM reservations WHERE id = :id', ['id' => $first['id']]));
        self::assertSame('ready', self::$db->scalar('SELECT status FROM reservations WHERE id = :id', ['id' => $second['id']]));
    }

    public function testRenewalLimitAndOverdueRules(): void
    {
        $student = $this->createStudent('R-1');
        [, [$barcode]] = $this->createBook('A');
        $issue = $this->app()->get(CirculationService::class)->issueCopy($student, $barcode);
        $id = (int) $issue['id'];

        $renewed = $this->app('2026-03-10', $this->studentActor($student))->get(CirculationService::class)->renew($id);
        self::assertSame('2026-03-24', $renewed['due_date']);
        self::assertSame(1, $renewed['renew_count']);

        $this->assertConflict('OVERDUE', fn () => $this->app('2026-03-30')->get(CirculationService::class)->renew($id));
    }

    public function testMarkLostChargesReplacementFeeAndRemovesCopy(): void
    {
        $student = $this->createStudent('R-1');
        [$bookId, [$barcode]] = $this->createBook('A', 2);
        $issue = $this->app()->get(CirculationService::class)->issueCopy($student, $barcode);

        $result = $this->app('2026-03-17')->get(CirculationService::class)->markLost((int) $issue['id']);

        self::assertSame(510.0, $result['fine_amount']); // 500 fee + 2 days x 5
        self::assertSame('lost', self::$db->scalar('SELECT status FROM book_copies WHERE barcode = :b', ['b' => $barcode]));
        self::assertSame(
            ['total_copies' => 1, 'available_copies' => 1],
            self::$db->one('SELECT total_copies, available_copies FROM books WHERE id = :id', ['id' => $bookId])
        );
    }

    public function testOverdueJobFlagsLoansAndNotifiesBorrowers(): void
    {
        $student = $this->createStudent('R-1');
        [, [$barcode]] = $this->createBook('A');
        $issue = $this->app()->get(CirculationService::class)->issueCopy($student, $barcode);

        self::assertSame(0, $this->app('2026-03-15')->get(CirculationService::class)->processOverdue());
        self::assertSame(1, $this->app('2026-03-16')->get(CirculationService::class)->processOverdue());
        self::assertSame(0, $this->app('2026-03-17')->get(CirculationService::class)->processOverdue(), 'Already flagged loans are not notified twice');

        self::assertSame('overdue', self::$db->scalar('SELECT status FROM issues WHERE id = :id', ['id' => $issue['id']]));
        self::assertSame(1, (int) self::$db->scalar("SELECT COUNT(*) FROM notifications WHERE title = 'Book overdue'"));
    }

    private function assertConflict(string $code, callable $action): void
    {
        try {
            $action();
            self::fail("Expected a {$code} conflict");
        } catch (ConflictException $e) {
            self::assertSame($code, $e->errorCode);
        }
    }
}
