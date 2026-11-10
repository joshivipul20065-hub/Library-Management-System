<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Http
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRESERVE_ZERO_FRACTION;

    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, self::JSON_FLAGS));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function noContent(ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(204);
    }

    /** @return Row */
    public static function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    public static function id(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new NotFoundException();
        }

        return (int) $value;
    }

    public static function wantsCsv(ServerRequestInterface $request): bool
    {
        return ($request->getQueryParams()['format'] ?? null) === 'csv';
    }

    public static function query(ServerRequestInterface $request, string $key): ?string
    {
        $value = $request->getQueryParams()[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
