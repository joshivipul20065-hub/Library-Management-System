<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Clock;
use App\Repositories\BookRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;

/** Moves pending reservations to "ready" while there are copies not already on hold. */
class ReservationQueue
{
    public function __construct(
        private readonly BookRepositoryInterface $books,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Must be called inside a transaction (locks the book row).
     *
     * @return list<int> ids of reservations that became ready
     */
    public function promote(int $bookId): array
    {
        $book = $this->books->findById($bookId, true);
        if ($book === null) {
            return [];
        }

        $promoted = [];
        $holdHours = $this->settings->int('reservation_hold_hours');
        while ((int) $book['available_copies'] > $this->reservations->countReady($bookId)) {
            $next = $this->reservations->nextPending($bookId);
            if ($next === null) {
                break;
            }
            $expiresAt = $this->clock->now()->modify("+{$holdHours} hours")->format('Y-m-d H:i:s');
            $this->reservations->updateStatus($next->id, 'ready', $expiresAt);
            $this->notifications->notify(
                $next->studentUserId,
                'Reserved book ready for pickup',
                sprintf('"%s" is ready for pickup at the library desk. Collect it before %s UTC or the hold will expire.', $next->bookTitle, $expiresAt),
                true
            );
            $promoted[] = $next->id;
        }

        return $promoted;
    }
}
