<?php

declare(strict_types=1);

namespace App\Models;

final class Student
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $status,
        public readonly string $rollNo,
        public readonly string $department,
        public readonly int $year,
        public readonly ?string $phone,
        public readonly ?string $address,
        public readonly ?int $maxBooks,
        public readonly string $createdAt,
    ) {
    }

    /** @param Row $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['status'],
            (string) $row['roll_no'],
            (string) $row['department'],
            (int) $row['year'],
            isset($row['phone']) ? (string) $row['phone'] : null,
            isset($row['address']) ? (string) $row['address'] : null,
            isset($row['max_books']) ? (int) $row['max_books'] : null,
            (string) $row['created_at'],
        );
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /** @return Row */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'roll_no' => $this->rollNo,
            'department' => $this->department,
            'year' => $this->year,
            'phone' => $this->phone,
            'address' => $this->address,
            'max_books' => $this->maxBooks,
            'created_at' => $this->createdAt,
        ];
    }
}
