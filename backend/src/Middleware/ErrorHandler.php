<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\ApiException;
use App\Helpers\Http;
use PDOException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/** Converts every exception into `{ "error": { code, message, details } }` with the right HTTP status. */
final class ErrorHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $code, $message, $details] = $this->describe($exception);

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
            ]);
            if ($this->debug) {
                $details = [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile() . ':' . $exception->getLine(),
                ];
            }
        }

        $response = Http::json(
            $this->responses->createResponse(),
            ['error' => ['code' => $code, 'message' => $message, 'details' => (object) $details]],
            $status
        );
        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        return $response;
    }

    /** @return array{0: int, 1: string, 2: string, 3: array<string, mixed>} */
    private function describe(Throwable $e): array
    {
        if ($e instanceof ApiException) {
            return [$e->status, $e->errorCode, $e->getMessage(), $e->details];
        }
        if ($e instanceof HttpNotFoundException) {
            return [404, 'NOT_FOUND', 'Resource not found', []];
        }
        if ($e instanceof HttpMethodNotAllowedException) {
            return [405, 'METHOD_NOT_ALLOWED', 'Method not allowed', []];
        }
        if ($e instanceof HttpException) {
            return [$e->getCode(), 'HTTP_ERROR', $e->getMessage(), []];
        }
        if ($e instanceof PDOException) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($driverCode, [1205, 1213], true)) {
                return [409, 'TRY_AGAIN', 'The record is busy, please try again', []];
            }
            if ($driverCode === 1062) {
                return [409, 'DUPLICATE', 'A record with the same unique value already exists', []];
            }
        }

        return [500, 'INTERNAL_ERROR', 'An unexpected error occurred', []];
    }
}
