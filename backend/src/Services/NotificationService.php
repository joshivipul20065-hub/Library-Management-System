<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Repositories\NotificationRepositoryInterface;
use App\Repositories\UserRepositoryInterface;

class NotificationService
{
    public function __construct(
        private readonly Database $db,
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly MailService $mail,
    ) {
    }

    /** Stores an in-app notification and optionally emails it (after the current transaction commits). */
    public function notify(int $userId, string $title, string $body, bool $email = false): void
    {
        $this->notifications->create($userId, $title, $body);
        if (!$email) {
            return;
        }
        $user = $this->users->findById($userId);
        if ($user !== null) {
            $this->db->afterCommit(fn () => $this->mail->send($user->email, $user->name, $title, $body));
        }
    }

    /** @return array{data: list<Row>, meta: array<string, int>} */
    public function list(int $userId, ListQuery $query): array
    {
        $page = $this->notifications->paginateForUser($userId, $query);
        $page['meta']['unread'] = $this->notifications->unreadCount($userId);

        return $page;
    }

    public function markRead(int $id, int $userId): void
    {
        if (!$this->notifications->markRead($id, $userId)) {
            throw new NotFoundException('Notification not found');
        }
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
