<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

interface NotificationRepositoryInterface
{
    public function create(int $userId, string $title, string $body): int;

    /** @return Page */
    public function paginateForUser(int $userId, ListQuery $query): array;

    public function unreadCount(int $userId): int;

    public function markRead(int $id, int $userId): bool;

    public function markAllRead(int $userId): void;
}
