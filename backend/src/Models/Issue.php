<?php

declare(strict_types=1);

namespace App\Models;

final class Issue
{
    public const ACTIVE_STATUSES = ['issued', 'overdue'];

    public function __construct(
        public readonly int $id,
        public readonly int $copyId,
        public readonly int $studentId,
        public readonly int $bookId,
        public readonly int $studentUserId,
        public readonly string $bookTitle,
        public readonly string $issueDate,
        public readonly string $dueDate,
        public readonly ?string $returnDate,
        public readonly string $status,
        public readonly int $renewCount,
    ) {
    }

    /** @param Row $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['copy_id'],
            (int) $row['student_id'],
            (int) $row['book_id'],
            (int) $row['student_user_id'],
            (string) $row['book_title'],
            (string) $row['issue_date'],
            (string) $row['due_date'],
            isset($row['return_date']) ? (string) $row['return_date'] : null,
            (string) $row['status'],
            (int) $row['renew_count'],
        );
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
