<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $role,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $status,
        public readonly ?string $lastLoginAt,
    ) {
    }

    /** @param Row $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['role'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['status'],
            isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
        );
    }
}
