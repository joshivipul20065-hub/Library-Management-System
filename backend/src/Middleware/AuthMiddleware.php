<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthenticationException;
use App\Helpers\JwtService;
use App\Helpers\RequestContext;
use App\Models\AuthUser;
use App\Repositories\StudentRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Validates the Bearer access token and loads the current user (so role/status changes apply immediately). */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserRepositoryInterface $users,
        private readonly StudentRepositoryInterface $students,
        private readonly RequestContext $context,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            throw new AuthenticationException('Authentication required');
        }
        $user = $this->users->findById($this->jwt->userId($matches[1]))
            ?? throw new AuthenticationException('Account no longer exists');
        $studentId = $user->role === AuthUser::ROLE_STUDENT ? $this->students->findByUserId($user->id)?->id : null;
        $this->context->user = AuthUser::fromUser($user, $studentId);

        return $handler->handle($request);
    }
}
