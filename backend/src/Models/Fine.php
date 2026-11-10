<?php

declare(strict_types=1);

namespace App\Models;

final class Fine
{
    public function __construct(
        public readonly int $id,
        public readonly int $issueId,
        public readonly int $studentId,
        public readonly int $studentUserId,
        public readonly float $amount,
        public readonly string $reason,
        public readonly string $status,
    ) {
    }

    /** @param Row $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['issue_id'],
            (int) $row['student_id'],
            (int) $row['student_user_id'],
            (float) $row['amount'],
            (string) $row['reason'],
            (string) $row['status'],
        );
    }
}
