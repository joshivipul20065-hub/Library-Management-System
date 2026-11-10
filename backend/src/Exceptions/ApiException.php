<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Base class for errors that are safe to show to API clients. */
class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = 'BAD_REQUEST',
        public readonly array $details = [],
    ) {
        parent::__construct($message, $status);
    }
}
