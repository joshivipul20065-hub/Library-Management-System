<?php

declare(strict_types=1);

namespace App\Models;

final class BookCopy
{
    public function __construct(
        public readonly int $id,
        public readonly int $bookId,
        public readonly string $barcode,
        public readonly string $status,
        public readonly string $bookTitle,
    ) {
    }

    /** @param Row $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['book_id'],
            (string) $row['barcode'],
            (string) $row['status'],
            (string) ($row['book_title'] ?? ''),
        );
    }

    /** @return Row */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->bookId,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'book_title' => $this->bookTitle,
        ];
    }
}
