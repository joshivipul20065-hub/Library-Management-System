<?php

declare(strict_types=1);

namespace App\Repositories;

interface TokenRepositoryInterface
{
    public function storeRefreshToken(int $userId, string $tokenHash, string $expiresAt): void;

    /** @return Row|null */
    public function findRefreshToken(string $tokenHash): ?array;

    public function revokeRefreshToken(int $id): void;

    public function revokeAllForUser(int $userId): void;

    public function storePasswordReset(int $userId, string $tokenHash, string $expiresAt): void;

    /** @return Row|null */
    public function findValidPasswordReset(string $tokenHash): ?array;

    public function markPasswordResetUsed(int $id): void;

    public function invalidatePasswordResets(int $userId): void;
}
