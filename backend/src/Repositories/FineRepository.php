<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Fine;

final class FineRepository extends BaseRepository implements FineRepositoryInterface
{
    public function paginate(ListQuery $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = $query->filter('status'), ['unpaid', 'paid', 'waived'], true)) {
            $where[] = 'f.status = :status';
            $params['status'] = $status;
        }
        if (($studentId = $query->filter('student_id')) !== null) {
            $where[] = 'i.student_id = :student_id';
            $params['student_id'] = (int) $studentId;
        }
        if (($from = $query->filter('from')) !== null) {
            $where[] = 'DATE(f.created_at) >= :from_date';
            $params['from_date'] = $from;
        }
        if (($to = $query->filter('to')) !== null) {
            $where[] = 'DATE(f.created_at) <= :to_date';
            $params['to_date'] = $to;
        }
        if (($q = $query->filter('q')) !== null) {
            $where[] = '(b.title LIKE :q1 OR u.name LIKE :q2 OR s.roll_no LIKE :q3)';
            $params += ['q1' => self::like($q), 'q2' => self::like($q), 'q3' => self::like($q)];
        }

        return $this->paginateQuery(
            'f.id, f.issue_id, f.amount, f.reason, f.status, f.paid_at, f.created_at,
             i.student_id, i.due_date, i.return_date, b.title AS book_title, s.roll_no, u.name AS student_name,
             p.name AS processed_by_name',
            'FROM fines f
             JOIN issues i ON i.id = f.issue_id
             JOIN book_copies bc ON bc.id = i.copy_id
             JOIN books b ON b.id = bc.book_id
             JOIN students s ON s.id = i.student_id
             JOIN users u ON u.id = s.user_id
             LEFT JOIN users p ON p.id = f.processed_by
             WHERE ' . implode(' AND ', $where),
            $params,
            $query
        );
    }

    public function findById(int $id, bool $forUpdate = false): ?Fine
    {
        $row = $this->db->one(
            'SELECT f.*, i.student_id, s.user_id AS student_user_id
             FROM fines f JOIN issues i ON i.id = f.issue_id JOIN students s ON s.id = i.student_id
             WHERE f.id = :id' . ($forUpdate ? ' FOR UPDATE OF f' : ''),
            ['id' => $id]
        );

        return $row === null ? null : Fine::fromRow($row);
    }

    public function create(int $issueId, float $amount, string $reason): int
    {
        return $this->db->insert(
            'INSERT INTO fines (issue_id, amount, reason) VALUES (:issue, :amount, :reason)',
            ['issue' => $issueId, 'amount' => number_format($amount, 2, '.', ''), 'reason' => $reason]
        );
    }

    public function setStatus(int $id, string $status, int $processedBy): void
    {
        $this->db->execute(
            'UPDATE fines SET status = :status, processed_by = :by, paid_at = NOW() WHERE id = :id',
            ['status' => $status, 'by' => $processedBy, 'id' => $id]
        );
    }

    public function unpaidTotalForStudent(int $studentId): float
    {
        return (float) $this->db->scalar(
            "SELECT COALESCE(SUM(f.amount), 0) FROM fines f JOIN issues i ON i.id = f.issue_id
             WHERE i.student_id = :id AND f.status = 'unpaid'",
            ['id' => $studentId]
        );
    }
}
