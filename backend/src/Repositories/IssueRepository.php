<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Issue;

final class IssueRepository extends BaseRepository implements IssueRepositoryInterface
{
    private const LIST_SELECT = "i.id, i.copy_id, i.student_id, i.issue_date, i.due_date, i.return_date, i.status, i.renew_count,
        bc.barcode, b.id AS book_id, b.title AS book_title, b.cover_url, b.isbn,
        s.roll_no, s.user_id AS student_user_id, u.name AS student_name, u.email AS student_email,
        ib.name AS issued_by_name,
        GREATEST(DATEDIFF(COALESCE(i.return_date, CURDATE()), i.due_date), 0) AS days_overdue,
        (SELECT COALESCE(SUM(f.amount), 0) FROM fines f WHERE f.issue_id = i.id) AS fine_amount";
    private const FROM = 'FROM issues i
        JOIN book_copies bc ON bc.id = i.copy_id
        JOIN books b ON b.id = bc.book_id
        JOIN students s ON s.id = i.student_id
        JOIN users u ON u.id = s.user_id
        LEFT JOIN users ib ON ib.id = i.issued_by';

    public function paginate(ListQuery $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        $status = $query->filter('status');
        if ($status === 'active') {
            $where[] = "i.status IN ('issued','overdue')";
        } elseif (in_array($status, ['issued', 'returned', 'overdue', 'lost'], true)) {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        if ($query->filter('overdue') === '1') {
            $where[] = "i.status IN ('issued','overdue') AND i.due_date < CURDATE()";
        }
        foreach (['student_id' => 'i.student_id', 'book_id' => 'b.id'] as $key => $column) {
            if (($value = $query->filter($key)) !== null) {
                $where[] = "{$column} = :{$key}";
                $params[$key] = (int) $value;
            }
        }
        if (($barcode = $query->filter('barcode')) !== null) {
            $where[] = 'bc.barcode = :barcode';
            $params['barcode'] = $barcode;
        }
        if (($from = $query->filter('from')) !== null) {
            $where[] = 'i.issue_date >= :from_date';
            $params['from_date'] = $from;
        }
        if (($to = $query->filter('to')) !== null) {
            $where[] = 'i.issue_date <= :to_date';
            $params['to_date'] = $to;
        }
        if (($dueBefore = $query->filter('due_before')) !== null) {
            $where[] = 'i.due_date <= :due_before';
            $params['due_before'] = $dueBefore;
        }
        if (($q = $query->filter('q')) !== null) {
            $where[] = '(b.title LIKE :q1 OR bc.barcode LIKE :q2 OR s.roll_no LIKE :q3 OR u.name LIKE :q4)';
            $params += ['q1' => self::like($q), 'q2' => self::like($q), 'q3' => self::like($q), 'q4' => self::like($q)];
        }

        return $this->paginateQuery(self::LIST_SELECT, self::FROM . ' WHERE ' . implode(' AND ', $where), $params, $query);
    }

    public function findById(int $id, bool $forUpdate = false): ?Issue
    {
        $row = $this->db->one(
            'SELECT i.*, bc.book_id, b.title AS book_title, s.user_id AS student_user_id
             FROM issues i
             JOIN book_copies bc ON bc.id = i.copy_id
             JOIN books b ON b.id = bc.book_id
             JOIN students s ON s.id = i.student_id
             WHERE i.id = :id' . ($forUpdate ? ' FOR UPDATE OF i' : ''),
            ['id' => $id]
        );

        return $row === null ? null : Issue::fromRow($row);
    }

    public function details(int $id): ?array
    {
        return $this->db->one('SELECT ' . self::LIST_SELECT . ' ' . self::FROM . ' WHERE i.id = :id', ['id' => $id]);
    }

    public function create(int $copyId, int $studentId, ?int $issuedBy, string $issueDate, string $dueDate): int
    {
        return $this->db->insert(
            'INSERT INTO issues (copy_id, student_id, issued_by, issue_date, due_date)
             VALUES (:copy, :student, :issued_by, :issue_date, :due_date)',
            ['copy' => $copyId, 'student' => $studentId, 'issued_by' => $issuedBy, 'issue_date' => $issueDate, 'due_date' => $dueDate]
        );
    }

    public function markReturned(int $id, string $returnDate): void
    {
        $this->db->execute(
            "UPDATE issues SET status = 'returned', return_date = :date WHERE id = :id",
            ['date' => $returnDate, 'id' => $id]
        );
    }

    public function markLost(int $id, string $date): void
    {
        $this->db->execute("UPDATE issues SET status = 'lost', return_date = :date WHERE id = :id", ['date' => $date, 'id' => $id]);
    }

    public function renew(int $id, string $dueDate): void
    {
        $this->db->execute(
            "UPDATE issues SET due_date = :due, renew_count = renew_count + 1, status = 'issued' WHERE id = :id",
            ['due' => $dueDate, 'id' => $id]
        );
    }

    public function countActiveForStudent(int $studentId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM issues WHERE student_id = :id AND status IN ('issued','overdue')",
            ['id' => $studentId]
        );
    }

    public function studentHasActiveIssueOfBook(int $studentId, int $bookId): bool
    {
        return $this->db->scalar(
            "SELECT 1 FROM issues i JOIN book_copies bc ON bc.id = i.copy_id
             WHERE i.student_id = :student AND bc.book_id = :book AND i.status IN ('issued','overdue') LIMIT 1",
            ['student' => $studentId, 'book' => $bookId]
        ) !== null;
    }

    public function countActiveForBook(int $bookId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM issues i JOIN book_copies bc ON bc.id = i.copy_id
             WHERE bc.book_id = :id AND i.status IN ('issued','overdue')",
            ['id' => $bookId]
        );
    }

    public function flagOverdue(string $today): array
    {
        $rows = $this->db->all(
            "SELECT i.id, i.due_date, b.title AS book_title, s.user_id AS student_user_id
             FROM issues i
             JOIN book_copies bc ON bc.id = i.copy_id
             JOIN books b ON b.id = bc.book_id
             JOIN students s ON s.id = i.student_id
             WHERE i.status = 'issued' AND i.due_date < :today
             FOR UPDATE OF i",
            ['today' => $today]
        );
        if ($rows !== []) {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $this->db->execute('UPDATE issues SET status = \'overdue\' WHERE id IN (' . implode(',', $ids) . ')');
        }

        return $rows;
    }
}
