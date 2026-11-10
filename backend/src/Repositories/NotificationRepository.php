<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

final class NotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    public function create(int $userId, string $title, string $body): int
    {
        return $this->db->insert(
            'INSERT INTO notifications (user_id, title, body) VALUES (:user, :title, :body)',
            ['user' => $userId, 'title' => mb_substr($title, 0, 150), 'body' => mb_substr($body, 0, 1000)]
        );
    }

    public function paginateForUser(int $userId, ListQuery $query): array
    {
        $where = 'user_id = :user';
        if ($query->filter('unread') === '1') {
            $where .= ' AND read_at IS NULL';
        }

        return $this->paginateQuery('id, title, body, read_at, created_at', "FROM notifications WHERE {$where}", ['user' => $userId], $query);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM notifications WHERE user_id = :user AND read_at IS NULL', ['user' => $userId]);
    }

    public function markRead(int $id, int $userId): bool
    {
        return $this->db->execute(
            'UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = :id AND user_id = :user',
            ['id' => $id, 'user' => $userId]
        ) > 0 || $this->db->scalar('SELECT 1 FROM notifications WHERE id = :id AND user_id = :user', ['id' => $id, 'user' => $userId]) !== null;
    }

    public function markAllRead(int $userId): void
    {
        $this->db->execute('UPDATE notifications SET read_at = NOW() WHERE user_id = :user AND read_at IS NULL', ['user' => $userId]);
    }
}
