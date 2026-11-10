<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

final class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $row = $this->db->one('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);

        return $row === null ? null : User::fromRow($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->one('SELECT * FROM users WHERE email = :email AND deleted_at IS NULL', ['email' => $email]);

        return $row === null ? null : User::fromRow($row);
    }

    public function emailExists(string $email, ?int $exceptUserId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM users WHERE email = :email AND id <> :id',
            ['email' => $email, 'id' => $exceptUserId ?? 0]
        ) !== null;
    }

    public function create(string $role, string $name, string $email, string $passwordHash): int
    {
        return $this->db->insert(
            'INSERT INTO users (role, name, email, password_hash) VALUES (:role, :name, :email, :hash)',
            ['role' => $role, 'name' => $name, 'email' => $email, 'hash' => $passwordHash]
        );
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->db->execute('UPDATE users SET name = :name, email = :email WHERE id = :id', ['name' => $name, 'email' => $email, 'id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->execute('UPDATE users SET status = :status WHERE id = :id', ['status' => $status, 'id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE users SET password_hash = :hash WHERE id = :id', ['hash' => $passwordHash, 'id' => $id]);
    }

    public function recordLogin(int $id): void
    {
        $this->db->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function softDelete(int $id): void
    {
        // Free the unique email so the address can be registered again.
        $this->db->execute(
            "UPDATE users SET deleted_at = NOW(), status = 'suspended',
                email = LEFT(CONCAT('deleted-', id, '-', email), 190) WHERE id = :id",
            ['id' => $id]
        );
    }
}
