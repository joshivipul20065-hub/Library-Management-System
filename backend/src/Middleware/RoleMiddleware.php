<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthorizationException;
use App\Helpers\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Role-based access control; must run after AuthMiddleware. */
final class RoleMiddleware implements MiddlewareInterface
{
    /** @param list<string> $roles */
    public function __construct(private readonly RequestContext $context, private readonly array $roles)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($this->context->user()->role, $this->roles, true)) {
            throw new AuthorizationException();
        }

        return $handler->handle($request);
    }
}
