<?php

declare(strict_types=1);

namespace App\Repositories;

final class TokenRepository extends BaseRepository implements TokenRepositoryInterface
{
    public function storeRefreshToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $this->db->insert(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)',
            ['user_id' => $userId, 'hash' => $tokenHash, 'expires' => $expiresAt]
        );
    }

    public function findRefreshToken(string $tokenHash): ?array
    {
        return $this->db->one('SELECT * FROM refresh_tokens WHERE token_hash = :hash FOR UPDATE', ['hash' => $tokenHash]);
    }

    public function revokeRefreshToken(int $id): void
    {
        $this->db->execute('UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL', ['id' => $id]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->db->execute('UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL', ['id' => $userId]);
    }

    public function storePasswordReset(int $userId, string $tokenHash, string $expiresAt): void
    {
        $this->db->insert(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :hash, :expires)',
            ['user_id' => $userId, 'hash' => $tokenHash, 'expires' => $expiresAt]
        );
    }

    public function findValidPasswordReset(string $tokenHash): ?array
    {
        return $this->db->one(
            'SELECT * FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW() FOR UPDATE',
            ['hash' => $tokenHash]
        );
    }

    public function markPasswordResetUsed(int $id): void
    {
        $this->db->execute('UPDATE password_resets SET used_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function invalidatePasswordResets(int $userId): void
    {
        $this->db->execute('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL', ['id' => $userId]);
    }
}
