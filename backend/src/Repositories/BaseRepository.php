<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Helpers\Paginated;

abstract class BaseRepository
{
    public function __construct(protected readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @return Page
     */
    protected function paginateQuery(string $select, string $fromWhere, array $params, ListQuery $query, string $groupBy = ''): array
    {
        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM (SELECT 1 AS one {$fromWhere} {$groupBy}) AS counted", $params);
        $rows = $this->db->all(
            "SELECT {$select} {$fromWhere} {$groupBy} ORDER BY {$query->orderBy()} LIMIT {$query->limit} OFFSET {$query->offset()}",
            $params
        );

        return Paginated::of($rows, $total, $query);
    }

    protected static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
