<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\ApiException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Parses JSON request bodies into the parsed body; rejects malformed JSON with 400. */
final class JsonBodyMiddleware implements MiddlewareInterface
{
    private const MAX_BYTES = 1024 * 1024;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            $raw = (string) $request->getBody();
            if (strlen($raw) > self::MAX_BYTES) {
                throw new ApiException('Request body is too large', 413, 'PAYLOAD_TOO_LARGE');
            }
            if (trim($raw) !== '') {
                try {
                    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new ApiException('Request body is not valid JSON', 400, 'INVALID_JSON');
                }
                if (!is_array($data)) {
                    throw new ApiException('Request body must be a JSON object', 400, 'INVALID_JSON');
                }
                $request = $request->withParsedBody($data);
            }
        }

        return $handler->handle($request);
    }
}
