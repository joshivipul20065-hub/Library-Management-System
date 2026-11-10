<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Clock;
use App\Models\Reservation;
use App\Repositories\BookRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Services\NotificationService;
use App\Services\ReservationQueue;
use App\Services\SettingsService;
use PHPUnit\Framework\TestCase;

final class ReservationQueueTest extends TestCase
{
    public function testPromotesPendingReservationsWhileUnheldCopiesRemain(): void
    {
        $books = $this->createMock(BookRepositoryInterface::class);
        $books->method('findById')->willReturn(['id' => 7, 'available_copies' => 2]);

        $reservations = $this->createMock(ReservationRepositoryInterface::class);
        $reservations->method('countReady')->willReturnOnConsecutiveCalls(0, 1, 2);
        $reservations->method('nextPending')->willReturnOnConsecutiveCalls(
            new Reservation(11, 7, 1, 101, 'Clean Code', 'pending', null),
            new Reservation(12, 7, 2, 102, 'Clean Code', 'pending', null),
        );
        $updated = [];
        $reservations->expects(self::exactly(2))->method('updateStatus')
            ->willReturnCallback(function (int $id, string $status, ?string $expires) use (&$updated): void {
                $updated[] = [$id, $status, $expires];
            });

        $settings = $this->createMock(SettingsService::class);
        $settings->method('int')->with('reservation_hold_hours')->willReturn(48);
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(self::exactly(2))->method('notify');

        $queue = new ReservationQueue($books, $reservations, $settings, $notifications, new Clock('2026-03-01 10:00:00'));

        self::assertSame([11, 12], $queue->promote(7));
        self::assertSame([[11, 'ready', '2026-03-03 10:00:00'], [12, 'ready', '2026-03-03 10:00:00']], $updated);
    }

    public function testDoesNothingWhenEveryCopyIsAlreadyHeld(): void
    {
        $books = $this->createMock(BookRepositoryInterface::class);
        $books->method('findById')->willReturn(['id' => 7, 'available_copies' => 1]);
        $reservations = $this->createMock(ReservationRepositoryInterface::class);
        $reservations->method('countReady')->willReturn(1);
        $reservations->expects(self::never())->method('nextPending');

        $queue = new ReservationQueue(
            $books,
            $reservations,
            $this->createMock(SettingsService::class),
            $this->createMock(NotificationService::class),
            new Clock('2026-03-01 10:00:00'),
        );

        self::assertSame([], $queue->promote(7));
    }
}
