<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Student;

interface StudentRepositoryInterface
{
    public const SORTABLE = [
        'name' => 'u.name',
        'roll_no' => 's.roll_no',
        'department' => 's.department',
        'year' => 's.year',
        'created_at' => 's.created_at',
    ];

    /** @return Page */
    public function paginate(ListQuery $query): array;

    public function findById(int $id, bool $forUpdate = false): ?Student;

    public function findByUserId(int $userId): ?Student;

    public function findByRollNo(string $rollNo): ?Student;

    public function rollNoExists(string $rollNo, ?int $exceptStudentId = null): bool;

    /** @param Row $data */
    public function create(int $userId, array $data): int;

    /** @param Row $data */
    public function update(int $id, array $data): void;

    public function updateContact(int $id, ?string $phone, ?string $address): void;
}
