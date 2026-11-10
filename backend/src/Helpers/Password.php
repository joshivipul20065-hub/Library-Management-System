<?php

declare(strict_types=1);

namespace App\Helpers;

final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    /** Returns an error message, or null when the password is acceptable. */
    public static function weakness(string $plain): ?string
    {
        if (strlen($plain) < 8 || strlen($plain) > 72) {
            return 'Password must be 8-72 characters long';
        }
        if (preg_match('/[A-Za-z]/', $plain) !== 1 || preg_match('/\d/', $plain) !== 1) {
            return 'Password must contain at least one letter and one number';
        }

        return null;
    }

    public static function random(): string
    {
        return bin2hex(random_bytes(12)) . 'A1';
    }
}
