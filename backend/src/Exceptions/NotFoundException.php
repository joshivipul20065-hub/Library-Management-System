<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'Resource not found', string $errorCode = 'NOT_FOUND', array $details = [])
    {
        parent::__construct($message, 404, $errorCode, $details);
    }
}
