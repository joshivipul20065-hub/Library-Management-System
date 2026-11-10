<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Pagination, sorting and filtering parameters for list endpoints.
 * Sort columns are always resolved through a whitelist, so they are safe to interpolate.
 */
final class ListQuery
{
    public const MAX_LIMIT = 100;

    /** @param array<string, string> $filters */
    public function __construct(
        public readonly int $page,
        public readonly int $limit,
        private readonly string $sortExpression,
        private readonly string $direction,
        private readonly array $filters = [],
    ) {
    }

    /** @param array<string, string> $sortable public sort key => trusted SQL expression */
    public static function fromRequest(
        ServerRequestInterface $request,
        array $sortable,
        string $defaultSort,
        string $defaultDirection = 'desc',
    ): self {
        $params = $request->getQueryParams();
        $filters = [];
        foreach ($params as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $filters[(string) $key] = trim($value);
            }
        }

        $sort = $filters['sort'] ?? $defaultSort;
        if (!isset($sortable[$sort])) {
            $sort = $defaultSort;
        }
        $order = strtolower($filters['order'] ?? $defaultDirection) === 'asc' ? 'ASC' : 'DESC';

        return new self(
            max(1, (int) ($filters['page'] ?? 1)),
            min(self::MAX_LIMIT, max(1, (int) ($filters['limit'] ?? 20))),
            $sortable[$sort],
            $order,
            $filters,
        );
    }

    /** @param array<string, string> $filters */
    public static function simple(string $sortExpression, string $direction = 'DESC', array $filters = [], int $limit = 20): self
    {
        return new self(1, $limit, $sortExpression, $direction, $filters);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function orderBy(): string
    {
        return $this->sortExpression . ' ' . $this->direction;
    }

    public function filter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
    }

    public function withFilter(string $key, string $value): self
    {
        $filters = $this->filters;
        $filters[$key] = $value;

        return new self($this->page, $this->limit, $this->sortExpression, $this->direction, $filters);
    }
}
