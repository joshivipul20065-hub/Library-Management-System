<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ClientIp;
use App\Helpers\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/** Outermost middleware: resolves the client IP and logs one line per request. */
final class RequestLogMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger, private readonly RequestContext $context)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->context->ip = ClientIp::resolve($request);
        $started = microtime(true);
        $response = $handler->handle($request);

        $this->logger->info('{method} {path} {status}', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'ip' => $this->context->ip,
            'user_id' => $this->context->user?->id,
        ]);

        return $response;
    }
}
