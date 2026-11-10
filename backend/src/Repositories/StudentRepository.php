<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Student;

final class StudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    private const SELECT = 'SELECT s.*, u.name, u.email, u.status FROM students s JOIN users u ON u.id = s.user_id';

    public function paginate(ListQuery $query): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if (($q = $query->filter('q')) !== null) {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR s.roll_no LIKE :q3)';
            $params += ['q1' => self::like($q), 'q2' => self::like($q), 'q3' => self::like($q)];
        }
        if (($department = $query->filter('department')) !== null) {
            $where[] = 's.department = :department';
            $params['department'] = $department;
        }
        if (($year = $query->filter('year')) !== null) {
            $where[] = 's.year = :year';
            $params['year'] = (int) $year;
        }
        if (in_array($status = $query->filter('status'), ['active', 'suspended'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        $select = "s.id, s.user_id, s.roll_no, s.department, s.year, s.phone, s.address, s.max_books, s.created_at,
            u.name, u.email, u.status, u.last_login_at,
            (SELECT COUNT(*) FROM issues i WHERE i.student_id = s.id AND i.status IN ('issued','overdue')) AS active_issues,
            (SELECT COALESCE(SUM(f.amount), 0) FROM fines f JOIN issues fi ON fi.id = f.issue_id
                WHERE fi.student_id = s.id AND f.status = 'unpaid') AS unpaid_fines";

        return $this->paginateQuery(
            $select,
            'FROM students s JOIN users u ON u.id = s.user_id WHERE ' . implode(' AND ', $where),
            $params,
            $query
        );
    }

    public function findById(int $id, bool $forUpdate = false): ?Student
    {
        $row = $this->db->one(
            self::SELECT . ' WHERE s.id = :id AND u.deleted_at IS NULL' . ($forUpdate ? ' FOR UPDATE OF s' : ''),
            ['id' => $id]
        );

        return $row === null ? null : Student::fromRow($row);
    }

    public function findByUserId(int $userId): ?Student
    {
        $row = $this->db->one(self::SELECT . ' WHERE s.user_id = :id AND u.deleted_at IS NULL', ['id' => $userId]);

        return $row === null ? null : Student::fromRow($row);
    }

    public function findByRollNo(string $rollNo): ?Student
    {
        $row = $this->db->one(self::SELECT . ' WHERE s.roll_no = :roll AND u.deleted_at IS NULL', ['roll' => $rollNo]);

        return $row === null ? null : Student::fromRow($row);
    }

    public function rollNoExists(string $rollNo, ?int $exceptStudentId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM students WHERE roll_no = :roll AND id <> :id',
            ['roll' => $rollNo, 'id' => $exceptStudentId ?? 0]
        ) !== null;
    }

    public function create(int $userId, array $data): int
    {
        return $this->db->insert(
            'INSERT INTO students (user_id, roll_no, department, year, phone, address, max_books)
             VALUES (:user_id, :roll_no, :department, :year, :phone, :address, :max_books)',
            [
                'user_id' => $userId,
                'roll_no' => $data['roll_no'],
                'department' => $data['department'],
                'year' => $data['year'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'max_books' => $data['max_books'] ?? null,
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE students SET roll_no = :roll_no, department = :department, year = :year, phone = :phone,
                address = :address, max_books = :max_books WHERE id = :id',
            [
                'roll_no' => $data['roll_no'],
                'department' => $data['department'],
                'year' => $data['year'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'max_books' => $data['max_books'] ?? null,
                'id' => $id,
            ]
        );
    }

    public function updateContact(int $id, ?string $phone, ?string $address): void
    {
        $this->db->execute(
            'UPDATE students SET phone = :phone, address = :address WHERE id = :id',
            ['phone' => $phone, 'address' => $address, 'id' => $id]
        );
    }
}
