<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class FineCalculator
{
    /** Whole days between the due date and $onDate; 0 when not late. Dates are Y-m-d. */
    public function daysOverdue(string $dueDate, string $onDate): int
    {
        $interval = (new DateTimeImmutable($dueDate))->diff(new DateTimeImmutable($onDate));

        return $interval->invert === 1 ? 0 : (int) $interval->days;
    }

    public function overdueFine(string $dueDate, string $onDate, float $finePerDay): float
    {
        return round($this->daysOverdue($dueDate, $onDate) * max(0.0, $finePerDay), 2);
    }
}
