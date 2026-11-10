<?php

declare(strict_types=1);

namespace App\Models;

/** The authenticated principal for the current request. */
final class AuthUser
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_LIBRARIAN = 'librarian';
    public const ROLE_STUDENT = 'student';

    public function __construct(
        public readonly int $id,
        public readonly string $role,
        public readonly string $name,
        public readonly string $email,
        public readonly string $status,
        public readonly ?int $studentId,
    ) {
    }

    public static function fromUser(User $user, ?int $studentId): self
    {
        return new self($user->id, $user->role, $user->name, $user->email, $user->status, $studentId);
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_LIBRARIAN;
    }

    /** @return Row */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'student_id' => $this->studentId,
        ];
    }
}
