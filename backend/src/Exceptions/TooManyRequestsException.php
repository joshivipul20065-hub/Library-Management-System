<?php

declare(strict_types=1);

namespace App\Exceptions;

final class TooManyRequestsException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'Too many requests, please try again later', string $errorCode = 'TOO_MANY_REQUESTS', array $details = [])
    {
        parent::__construct($message, 429, $errorCode, $details);
    }
}
