<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Reservation;

final class ReservationRepository extends BaseRepository implements ReservationRepositoryInterface
{
    private const SELECT = 'SELECT r.*, b.title AS book_title, s.user_id AS student_user_id
        FROM reservations r JOIN books b ON b.id = r.book_id JOIN students s ON s.id = r.student_id';

    public function paginate(ListQuery $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        $status = $query->filter('status');
        if ($status === 'active') {
            $where[] = "r.status IN ('pending','ready')";
        } elseif (in_array($status, ['pending', 'ready', 'fulfilled', 'cancelled', 'expired'], true)) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }
        foreach (['student_id' => 'r.student_id', 'book_id' => 'r.book_id'] as $key => $column) {
            if (($value = $query->filter($key)) !== null) {
                $where[] = "{$column} = :{$key}";
                $params[$key] = (int) $value;
            }
        }
        if (($q = $query->filter('q')) !== null) {
            $where[] = '(b.title LIKE :q1 OR u.name LIKE :q2 OR s.roll_no LIKE :q3)';
            $params += ['q1' => self::like($q), 'q2' => self::like($q), 'q3' => self::like($q)];
        }

        $select = "r.id, r.book_id, r.student_id, r.status, r.created_at, r.expires_at,
            b.title AS book_title, b.cover_url, b.available_copies, s.roll_no, u.name AS student_name,
            CASE WHEN r.status = 'pending' THEN (
                SELECT COUNT(*) FROM reservations r2 WHERE r2.book_id = r.book_id AND r2.status = 'pending' AND r2.id <= r.id
            ) END AS queue_position";

        return $this->paginateQuery(
            $select,
            'FROM reservations r JOIN books b ON b.id = r.book_id JOIN students s ON s.id = r.student_id
             JOIN users u ON u.id = s.user_id WHERE ' . implode(' AND ', $where),
            $params,
            $query
        );
    }

    public function findById(int $id, bool $forUpdate = false): ?Reservation
    {
        $row = $this->db->one(self::SELECT . ' WHERE r.id = :id' . ($forUpdate ? ' FOR UPDATE OF r' : ''), ['id' => $id]);

        return $row === null ? null : Reservation::fromRow($row);
    }

    public function findActive(int $studentId, int $bookId): ?Reservation
    {
        $row = $this->db->one(
            self::SELECT . " WHERE r.student_id = :student AND r.book_id = :book AND r.status IN ('pending','ready')
             ORDER BY r.id LIMIT 1 FOR UPDATE OF r",
            ['student' => $studentId, 'book' => $bookId]
        );

        return $row === null ? null : Reservation::fromRow($row);
    }

    public function countActiveForStudent(int $studentId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM reservations WHERE student_id = :id AND status IN ('pending','ready')",
            ['id' => $studentId]
        );
    }

    public function countReady(int $bookId, ?int $excludeStudentId = null): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM reservations WHERE book_id = :book AND status = 'ready' AND student_id <> :student",
            ['book' => $bookId, 'student' => $excludeStudentId ?? 0]
        );
    }

    public function hasActiveForOthers(int $bookId, int $studentId): bool
    {
        return $this->db->scalar(
            "SELECT 1 FROM reservations WHERE book_id = :book AND student_id <> :student AND status IN ('pending','ready') LIMIT 1",
            ['book' => $bookId, 'student' => $studentId]
        ) !== null;
    }

    public function nextPending(int $bookId): ?Reservation
    {
        $row = $this->db->one(
            self::SELECT . " WHERE r.book_id = :book AND r.status = 'pending' ORDER BY r.id LIMIT 1 FOR UPDATE OF r",
            ['book' => $bookId]
        );

        return $row === null ? null : Reservation::fromRow($row);
    }

    public function create(int $bookId, int $studentId): int
    {
        return $this->db->insert(
            'INSERT INTO reservations (book_id, student_id) VALUES (:book, :student)',
            ['book' => $bookId, 'student' => $studentId]
        );
    }

    public function updateStatus(int $id, string $status, ?string $expiresAt = null): void
    {
        $this->db->execute(
            'UPDATE reservations SET status = :status, expires_at = :expires WHERE id = :id',
            ['status' => $status, 'expires' => $expiresAt, 'id' => $id]
        );
    }

    public function findExpired(string $now): array
    {
        $rows = $this->db->all(
            self::SELECT . " WHERE r.status = 'ready' AND r.expires_at < :now ORDER BY r.id FOR UPDATE OF r",
            ['now' => $now]
        );

        return array_map(Reservation::fromRow(...), $rows);
    }

    public function cancelActiveForStudent(int $studentId): array
    {
        $bookIds = $this->db->all(
            "SELECT DISTINCT book_id FROM reservations WHERE student_id = :id AND status IN ('pending','ready') FOR UPDATE",
            ['id' => $studentId]
        );
        $this->db->execute(
            "UPDATE reservations SET status = 'cancelled', expires_at = NULL WHERE student_id = :id AND status IN ('pending','ready')",
            ['id' => $studentId]
        );

        return array_map(static fn (array $row): int => (int) $row['book_id'], $bookIds);
    }

    public function cancelActiveForBook(int $bookId): void
    {
        $this->db->execute(
            "UPDATE reservations SET status = 'cancelled', expires_at = NULL WHERE book_id = :id AND status IN ('pending','ready')",
            ['id' => $bookId]
        );
    }
}
