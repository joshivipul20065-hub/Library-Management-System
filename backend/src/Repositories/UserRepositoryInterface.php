<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function emailExists(string $email, ?int $exceptUserId = null): bool;

    public function create(string $role, string $name, string $email, string $passwordHash): int;

    public function updateProfile(int $id, string $name, string $email): void;

    public function setStatus(int $id, string $status): void;

    public function updatePassword(int $id, string $passwordHash): void;

    public function recordLogin(int $id): void;

    public function softDelete(int $id): void;
}
