<?php

declare(strict_types=1);

namespace App\Helpers;

final class Paginated
{
    /**
     * @param list<Row> $rows
     * @return Page
     */
    public static function of(array $rows, int $total, ListQuery $query): array
    {
        return [
            'data' => $rows,
            'meta' => [
                'page' => $query->page,
                'limit' => $query->limit,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $query->limit)),
            ],
        ];
    }
}
