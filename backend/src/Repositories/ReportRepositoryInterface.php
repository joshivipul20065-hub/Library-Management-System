<?php

declare(strict_types=1);

namespace App\Repositories;

interface ReportRepositoryInterface
{
    /** @return Row */
    public function summary(string $today): array;

    /** @return list<Row> */
    public function popularBooks(string $from, string $to, int $limit): array;

    /** @return list<Row> */
    public function overdue(string $today): array;

    /**
     * Issue and return counts per month (YYYY-MM) since $from.
     *
     * @return array{issues: array<string, int>, returns: array<string, int>}
     */
    public function monthlyCounts(string $from): array;

    /** @return list<Row> */
    public function topCategories(string $from, string $to, int $limit): array;

    /** @return list<Row> */
    public function recommendations(int $studentId, int $limit): array;
}
