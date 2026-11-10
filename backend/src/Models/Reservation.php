<?php

declare(strict_types=1);

namespace App\Models;

final class Reservation
{
    public const ACTIVE_STATUSES = ['pending', 'ready'];

    public function __construct(
        public readonly int $id,
        public readonly int $bookId,
        public readonly int $studentId,
        public readonly int $studentUserId,
        public readonly string $bookTitle,
        public readonly string $status,
        public readonly ?string $expiresAt,
    ) {
    }

    /** @param Row $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['book_id'],
            (int) $row['student_id'],
            (int) $row['student_user_id'],
            (string) $row['book_title'],
            (string) $row['status'],
            isset($row['expires_at']) ? (string) $row['expires_at'] : null,
        );
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /** @return Row */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->bookId,
            'student_id' => $this->studentId,
            'book_title' => $this->bookTitle,
            'status' => $this->status,
            'expires_at' => $this->expiresAt,
        ];
    }
}
