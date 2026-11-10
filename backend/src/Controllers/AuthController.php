<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AuthenticationException;
use App\Helpers\Env;
use App\Helpers\Http;
use App\Helpers\RequestContext;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    private const COOKIE = 'refresh_token';
    private const COOKIE_PATH = '/api/v1/auth';

    public function __construct(private readonly AuthService $auth, private readonly RequestContext $context)
    {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->auth->login(Http::body($request));

        return $this->withSession($response, $result);
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertCsrfHeader($request);
        $token = $request->getCookieParams()[self::COOKIE] ?? null;
        if (!is_string($token) || $token === '') {
            throw new AuthenticationException('No active session', 'NO_SESSION');
        }
        try {
            $result = $this->auth->refresh($token);
        } catch (AuthenticationException $e) {
            // Clear the dead cookie so the browser stops sending it.
            return Http::json(
                $response,
                ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'details' => (object) []]],
                401
            )->withHeader('Set-Cookie', $this->cookie('', 0));
        }

        return $this->withSession($response, $result);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertCsrfHeader($request);
        $token = $request->getCookieParams()[self::COOKIE] ?? null;
        $this->auth->logout(is_string($token) ? $token : null);

        return $response->withStatus(204)->withHeader('Set-Cookie', $this->cookie('', 0));
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->auth->me($this->context->user()->id)]);
    }

    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $email = Http::body($request)['email'] ?? '';
        $this->auth->forgotPassword(is_string($email) ? $email : '');

        return Http::json($response, ['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->resetPassword(Http::body($request));

        return Http::json($response, ['message' => 'Password updated. You can now sign in.']);
    }

    public function changePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->auth->changePassword($this->context->user()->id, Http::body($request));

        return $this->withSession($response, $result);
    }

    /** @param array{access_token: string, expires_in: int, refresh_token: string, user: Row} $result */
    private function withSession(ResponseInterface $response, array $result): ResponseInterface
    {
        $response = Http::json($response, [
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
            'user' => $result['user'],
        ]);

        return $response->withHeader('Set-Cookie', $this->cookie($result['refresh_token'], $this->auth->refreshTtl()));
    }

    private function cookie(string $value, int $maxAge): string
    {
        $parts = [
            self::COOKIE . '=' . rawurlencode($value),
            'Path=' . self::COOKIE_PATH,
            'Max-Age=' . $maxAge,
            'HttpOnly',
            'SameSite=Strict',
        ];
        if (Env::bool('COOKIE_SECURE')) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    /**
     * The refresh cookie is SameSite=Strict; requiring a custom header additionally forces a CORS
     * preflight for any cross-origin attempt, so other sites cannot trigger a refresh or logout.
     */
    private function assertCsrfHeader(ServerRequestInterface $request): void
    {
        if ($request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            throw new AuthenticationException('Missing X-Requested-With header', 'CSRF_CHECK_FAILED');
        }
    }
}
