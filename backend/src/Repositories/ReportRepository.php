<?php

declare(strict_types=1);

namespace App\Repositories;

final class ReportRepository extends BaseRepository implements ReportRepositoryInterface
{
    private const AUTHORS = "(SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ')
        FROM book_authors ba JOIN authors a ON a.id = ba.author_id WHERE ba.book_id = b.id) AS authors";

    public function summary(string $today): array
    {
        $row = $this->db->one(
            "SELECT
                (SELECT COUNT(*) FROM books WHERE deleted_at IS NULL) AS total_titles,
                (SELECT COALESCE(SUM(total_copies), 0) FROM books WHERE deleted_at IS NULL) AS total_copies,
                (SELECT COALESCE(SUM(available_copies), 0) FROM books WHERE deleted_at IS NULL) AS available_copies,
                (SELECT COUNT(*) FROM issues WHERE status IN ('issued','overdue')) AS issued,
                (SELECT COUNT(*) FROM issues WHERE status IN ('issued','overdue') AND due_date < :today) AS overdue,
                (SELECT COUNT(*) FROM users WHERE role = 'student' AND deleted_at IS NULL) AS students,
                (SELECT COUNT(*) FROM users WHERE role = 'student' AND deleted_at IS NULL AND status = 'suspended') AS suspended_students,
                (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE status = 'paid') AS fines_collected,
                (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE status = 'unpaid') AS fines_outstanding,
                (SELECT COUNT(*) FROM reservations WHERE status IN ('pending','ready')) AS active_reservations",
            ['today' => $today]
        );

        return $row ?? [];
    }

    public function popularBooks(string $from, string $to, int $limit): array
    {
        return $this->db->all(
            'SELECT b.id, b.title, b.isbn, b.cover_url, c.name AS category_name, ' . self::AUTHORS . ', COUNT(i.id) AS issue_count
             FROM issues i
             JOIN book_copies bc ON bc.id = i.copy_id
             JOIN books b ON b.id = bc.book_id
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE i.issue_date BETWEEN :from_date AND :to_date
             GROUP BY b.id, b.title, b.isbn, b.cover_url, c.name
             ORDER BY issue_count DESC, b.title
             LIMIT ' . max(1, min(100, $limit)),
            ['from_date' => $from, 'to_date' => $to]
        );
    }

    public function overdue(string $today): array
    {
        return $this->db->all(
            "SELECT i.id, i.issue_date, i.due_date, DATEDIFF(:today1, i.due_date) AS days_overdue,
                b.title AS book_title, bc.barcode, s.roll_no, u.name AS student_name, u.email AS student_email, s.phone
             FROM issues i
             JOIN book_copies bc ON bc.id = i.copy_id
             JOIN books b ON b.id = bc.book_id
             JOIN students s ON s.id = i.student_id
             JOIN users u ON u.id = s.user_id
             WHERE i.status IN ('issued','overdue') AND i.due_date < :today2
             ORDER BY i.due_date",
            ['today1' => $today, 'today2' => $today]
        );
    }

    public function monthlyCounts(string $from): array
    {
        $result = ['issues' => [], 'returns' => []];
        $queries = [
            'issues' => "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month, COUNT(*) AS total FROM issues
                         WHERE issue_date >= :from_date GROUP BY month",
            'returns' => "SELECT DATE_FORMAT(return_date, '%Y-%m') AS month, COUNT(*) AS total FROM issues
                          WHERE status = 'returned' AND return_date >= :from_date GROUP BY month",
        ];
        foreach ($queries as $key => $sql) {
            foreach ($this->db->all($sql, ['from_date' => $from]) as $row) {
                $result[$key][(string) $row['month']] = (int) $row['total'];
            }
        }

        return $result;
    }

    public function topCategories(string $from, string $to, int $limit): array
    {
        return $this->db->all(
            'SELECT c.id, c.name, COUNT(i.id) AS issue_count
             FROM issues i
             JOIN book_copies bc ON bc.id = i.copy_id
             JOIN books b ON b.id = bc.book_id
             JOIN categories c ON c.id = b.category_id
             WHERE i.issue_date BETWEEN :from_date AND :to_date
             GROUP BY c.id, c.name
             ORDER BY issue_count DESC, c.name
             LIMIT ' . max(1, min(50, $limit)),
            ['from_date' => $from, 'to_date' => $to]
        );
    }

    public function recommendations(int $studentId, int $limit): array
    {
        // Popular titles the student has not borrowed, preferring their most-read categories.
        return $this->db->all(
            'SELECT b.id, b.title, b.cover_url, b.available_copies, c.name AS category_name, ' . self::AUTHORS . ',
                (SELECT COUNT(*) FROM issues pi JOIN book_copies pbc ON pbc.id = pi.copy_id WHERE pbc.book_id = b.id) AS popularity,
                (SELECT COUNT(*) FROM issues hi JOIN book_copies hbc ON hbc.id = hi.copy_id JOIN books hb ON hb.id = hbc.book_id
                    WHERE hi.student_id = :sid1 AND hb.category_id = b.category_id) AS affinity
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.deleted_at IS NULL
               AND b.id NOT IN (SELECT xbc.book_id FROM issues xi JOIN book_copies xbc ON xbc.id = xi.copy_id WHERE xi.student_id = :sid2)
             ORDER BY affinity DESC, popularity DESC, b.title
             LIMIT ' . max(1, min(24, $limit)),
            ['sid1' => $studentId, 'sid2' => $studentId]
        );
    }
}
