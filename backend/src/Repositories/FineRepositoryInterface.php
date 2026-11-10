<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Fine;

interface FineRepositoryInterface
{
    public const SORTABLE = ['created_at' => 'f.created_at', 'amount' => 'f.amount', 'status' => 'f.status', 'paid_at' => 'f.paid_at'];

    /**
     * Filters: status, student_id, q, from, to.
     *
     * @return Page
     */
    public function paginate(ListQuery $query): array;

    public function findById(int $id, bool $forUpdate = false): ?Fine;

    public function create(int $issueId, float $amount, string $reason): int;

    public function setStatus(int $id, string $status, int $processedBy): void;

    public function unpaidTotalForStudent(int $studentId): float;
}
