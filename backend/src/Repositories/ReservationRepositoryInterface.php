<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Reservation;

interface ReservationRepositoryInterface
{
    public const SORTABLE = ['created_at' => 'r.created_at', 'status' => 'r.status', 'expires_at' => 'r.expires_at', 'id' => 'r.id'];

    /**
     * Filters: status (or "active"), student_id, book_id, q.
     *
     * @return Page
     */
    public function paginate(ListQuery $query): array;

    public function findById(int $id, bool $forUpdate = false): ?Reservation;

    public function findActive(int $studentId, int $bookId): ?Reservation;

    public function countActiveForStudent(int $studentId): int;

    public function countReady(int $bookId, ?int $excludeStudentId = null): int;

    public function hasActiveForOthers(int $bookId, int $studentId): bool;

    public function nextPending(int $bookId): ?Reservation;

    public function create(int $bookId, int $studentId): int;

    public function updateStatus(int $id, string $status, ?string $expiresAt = null): void;

    /** @return list<Reservation> */
    public function findExpired(string $now): array;

    /**
     * Cancels every pending/ready reservation of a student and returns the affected book ids.
     *
     * @return list<int>
     */
    public function cancelActiveForStudent(int $studentId): array;

    public function cancelActiveForBook(int $bookId): void;
}
