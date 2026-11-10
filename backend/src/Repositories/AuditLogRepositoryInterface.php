<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

interface AuditLogRepositoryInterface
{
    public const SORTABLE = ['created_at' => 'l.id', 'action' => 'l.action', 'entity' => 'l.entity'];

    /** @param Row $metadata */
    public function create(?int $userId, string $action, string $entity, ?int $entityId, array $metadata, string $ip): void;

    /**
     * Filters: user_id, entity, action, from, to, q.
     *
     * @return Page
     */
    public function paginate(ListQuery $query): array;
}
