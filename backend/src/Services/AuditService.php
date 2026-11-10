<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ListQuery;
use App\Helpers\RequestContext;
use App\Repositories\AuditLogRepositoryInterface;

class AuditService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $logs,
        private readonly RequestContext $context,
    ) {
    }

    /** @param Row $metadata */
    public function log(string $action, string $entity, ?int $entityId = null, array $metadata = []): void
    {
        $this->logs->create($this->context->user?->id, $action, $entity, $entityId, $metadata, $this->context->ip);
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        $page = $this->logs->paginate($query);
        foreach ($page['data'] as $i => $row) {
            $page['data'][$i]['metadata'] = is_string($row['metadata']) ? json_decode($row['metadata'], true) : null;
        }

        return $page;
    }
}
