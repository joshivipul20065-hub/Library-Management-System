<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\TooManyRequestsException;
use App\Helpers\Database;
use App\Helpers\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Sliding-window limit per client IP, stored in MySQL so it holds across PHP workers. */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly RequestContext $context,
        private readonly string $name,
        private readonly int $maxHits,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $bucket = $this->name . ':' . $this->context->ip;
        $hits = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = :bucket AND created_at > NOW(3) - INTERVAL :window SECOND',
            ['bucket' => $bucket, 'window' => $this->windowSeconds]
        );
        if ($hits >= $this->maxHits) {
            throw new TooManyRequestsException('Too many attempts. Please wait a minute and try again.', 'RATE_LIMITED', [
                'retry_after' => $this->windowSeconds,
            ]);
        }
        $this->db->insert('INSERT INTO rate_limit_hits (bucket) VALUES (:bucket)', ['bucket' => $bucket]);
        // Opportunistic cleanup of old entries (about 1 request in 50).
        if (random_int(1, 50) === 1) {
            $this->db->execute('DELETE FROM rate_limit_hits WHERE created_at < NOW() - INTERVAL 1 DAY');
        }

        return $handler->handle($request)->withHeader('X-RateLimit-Limit', (string) $this->maxHits)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxHits - $hits - 1));
    }
}
