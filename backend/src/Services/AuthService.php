<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\Database;
use App\Helpers\JwtService;
use App\Helpers\Password;
use App\Models\AuthUser;
use App\Models\User;
use App\Repositories\StudentRepositoryInterface;
use App\Repositories\TokenRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;
use Psr\Log\LoggerInterface;

final class AuthService
{
    private const RESET_TTL_SECONDS = 3600;
    private const SETUP_TTL_SECONDS = 72 * 3600;
    /** A revoked refresh token presented again after this grace period is treated as stolen. */
    private const REUSE_GRACE_SECONDS = 30;

    private static ?string $dummyHash = null;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepositoryInterface $users,
        private readonly StudentRepositoryInterface $students,
        private readonly TokenRepositoryInterface $tokens,
        private readonly JwtService $jwt,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
        private readonly string $refreshSecret,
        private readonly int $refreshTtl,
        private readonly string $appUrl,
    ) {
    }

    /**
     * @param Row $input
     * @return array{access_token: string, expires_in: int, refresh_token: string, user: Row}
     */
    public function login(array $input): array
    {
        $data = Validator::validate($input, Rules::login());
        $user = $this->users->findByEmail((string) $data['email']);
        // Always verify against a hash so response timing does not reveal whether the email exists.
        $valid = Password::verify((string) $data['password'], $user->passwordHash ?? self::dummyHash());
        if ($user === null || !$valid) {
            $this->logger->notice('Failed login attempt', ['email_hash' => hash('sha256', (string) $data['email'])]);
            throw new AuthenticationException('Invalid email or password', 'INVALID_CREDENTIALS');
        }
        if (Password::needsRehash($user->passwordHash)) {
            $this->users->updatePassword($user->id, Password::hash((string) $data['password']));
        }
        $this->users->recordLogin($user->id);

        return $this->issueTokens($user);
    }

    /** @return array{access_token: string, expires_in: int, refresh_token: string, user: Row} */
    public function refresh(string $refreshToken): array
    {
        $outcome = $this->db->transaction(function () use ($refreshToken): array|string {
            $row = $this->tokens->findRefreshToken($this->hashToken($refreshToken));
            if ($row === null) {
                return 'invalid';
            }
            $userId = (int) $row['user_id'];
            if ($row['revoked_at'] !== null) {
                if (strtotime($row['revoked_at'] . ' UTC') < time() - self::REUSE_GRACE_SECONDS) {
                    $this->tokens->revokeAllForUser($userId);
                    $this->logger->warning('Refresh token reuse detected; all sessions revoked', ['user_id' => $userId]);
                }

                return 'invalid';
            }
            if (strtotime($row['expires_at'] . ' UTC') < time()) {
                return 'invalid';
            }
            $user = $this->users->findById($userId);
            if ($user === null) {
                return 'invalid';
            }
            $this->tokens->revokeRefreshToken((int) $row['id']);

            return $this->issueTokens($user);
        });

        if (!is_array($outcome)) {
            throw new AuthenticationException('Session expired, please sign in again', 'INVALID_REFRESH_TOKEN');
        }

        return $outcome;
    }

    public function logout(?string $refreshToken): void
    {
        if ($refreshToken === null || $refreshToken === '') {
            return;
        }
        $this->db->transaction(function () use ($refreshToken): void {
            $row = $this->tokens->findRefreshToken($this->hashToken($refreshToken));
            if ($row !== null) {
                $this->tokens->revokeRefreshToken((int) $row['id']);
            }
        });
    }

    /** @return Row */
    public function me(int $userId): array
    {
        $user = $this->users->findById($userId) ?? throw new NotFoundException('User not found');
        $student = $user->role === AuthUser::ROLE_STUDENT ? $this->students->findByUserId($user->id) : null;

        return AuthUser::fromUser($user, $student?->id)->toArray() + [
            'last_login_at' => $user->lastLoginAt,
            'student' => $student?->toArray(),
        ];
    }

    /** Always succeeds from the caller's perspective, so it cannot be used to discover accounts. */
    public function forgotPassword(string $email): void
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null) {
            return;
        }
        $this->sendResetLink($user, self::RESET_TTL_SECONDS, 'Reset your library password', 'We received a request to reset your password.');
    }

    /** Emails a new account holder a link to choose their own password. */
    public function sendAccountSetup(int $userId): void
    {
        $user = $this->users->findById($userId);
        if ($user !== null) {
            $this->sendResetLink($user, self::SETUP_TTL_SECONDS, 'Set up your library account', 'A library account has been created for you.');
        }
    }

    /** @param Row $input */
    public function resetPassword(array $input): void
    {
        $data = Validator::validate($input, Rules::resetPassword());
        $this->assertStrong((string) $data['password'], 'password');

        $this->db->transaction(function () use ($data): void {
            $row = $this->tokens->findValidPasswordReset(hash('sha256', (string) $data['token']));
            if ($row === null) {
                throw new ValidationException('This reset link is invalid or has expired', ['token' => 'Request a new reset link']);
            }
            $userId = (int) $row['user_id'];
            $this->users->updatePassword($userId, Password::hash((string) $data['password']));
            $this->tokens->markPasswordResetUsed((int) $row['id']);
            $this->tokens->invalidatePasswordResets($userId);
            $this->tokens->revokeAllForUser($userId);
        });
    }

    /**
     * Changes the password, revokes every other session and returns fresh tokens for this one.
     *
     * @param Row $input
     * @return array{access_token: string, expires_in: int, refresh_token: string, user: Row}
     */
    public function changePassword(int $userId, array $input): array
    {
        $data = Validator::validate($input, Rules::changePassword());
        $user = $this->users->findById($userId) ?? throw new NotFoundException('User not found');
        if (!Password::verify((string) $data['current_password'], $user->passwordHash)) {
            throw new ValidationException('Current password is incorrect', ['current_password' => 'Current password is incorrect']);
        }
        if ($data['current_password'] === $data['new_password']) {
            throw new ValidationException('Choose a different password', ['new_password' => 'New password must differ from the current one']);
        }
        $this->assertStrong((string) $data['new_password'], 'new_password');

        return $this->db->transaction(function () use ($user, $data): array {
            $this->users->updatePassword($user->id, Password::hash((string) $data['new_password']));
            $this->tokens->revokeAllForUser($user->id);

            return $this->issueTokens($user);
        });
    }

    public function revokeAllSessions(int $userId): void
    {
        $this->tokens->revokeAllForUser($userId);
    }

    public function refreshTtl(): int
    {
        return $this->refreshTtl;
    }

    public function assertStrong(string $password, string $field): void
    {
        $problem = Password::weakness($password);
        if ($problem !== null) {
            throw new ValidationException($problem, [$field => $problem]);
        }
    }

    /** @return array{access_token: string, expires_in: int, refresh_token: string, user: Row} */
    private function issueTokens(User $user): array
    {
        $studentId = $user->role === AuthUser::ROLE_STUDENT ? $this->students->findByUserId($user->id)?->id : null;
        $authUser = AuthUser::fromUser($user, $studentId);
        $refreshToken = bin2hex(random_bytes(32));
        $this->tokens->storeRefreshToken($user->id, $this->hashToken($refreshToken), gmdate('Y-m-d H:i:s', time() + $this->refreshTtl));

        return [
            'access_token' => $this->jwt->issue($authUser),
            'expires_in' => $this->jwt->ttl(),
            'refresh_token' => $refreshToken,
            'user' => $authUser->toArray(),
        ];
    }

    private function sendResetLink(User $user, int $ttl, string $subject, string $intro): void
    {
        $token = bin2hex(random_bytes(32));
        $this->db->transaction(function () use ($user, $token, $ttl): void {
            $this->tokens->invalidatePasswordResets($user->id);
            $this->tokens->storePasswordReset($user->id, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + $ttl));
        });
        $link = rtrim($this->appUrl, '/') . '/reset-password?token=' . $token;
        $hours = intdiv($ttl, 3600);
        $body = "Hello {$user->name},\n\n{$intro}\nChoose a password using this link (valid for {$hours} hour(s)):\n\n{$link}\n\n"
            . "If you did not expect this email you can ignore it.\n";
        $this->db->afterCommit(fn () => $this->mail->send($user->email, $user->name, $subject, $body));
    }

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, $this->refreshSecret);
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Password::hash(bin2hex(random_bytes(8)));
    }
}
