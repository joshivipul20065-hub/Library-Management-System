<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\AuthenticationException;
use App\Models\AuthUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    private const ALGORITHM = 'HS256';
    private const ISSUER = 'library-api';

    public function __construct(private readonly string $secret, private readonly int $ttlSeconds)
    {
    }

    public function issue(AuthUser $user): string
    {
        $now = time();

        return JWT::encode([
            'iss' => self::ISSUER,
            'sub' => (string) $user->id,
            'role' => $user->role,
            'sid' => $user->studentId,
            'typ' => 'access',
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], $this->secret, self::ALGORITHM);
    }

    public function userId(string $token): int
    {
        try {
            $claims = (array) JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable) {
            throw new AuthenticationException('Invalid or expired access token');
        }
        if (($claims['typ'] ?? null) !== 'access' || ($claims['iss'] ?? null) !== self::ISSUER || !isset($claims['sub'])) {
            throw new AuthenticationException('Invalid access token');
        }

        return (int) $claims['sub'];
    }

    public function ttl(): int
    {
        return $this->ttlSeconds;
    }
}
