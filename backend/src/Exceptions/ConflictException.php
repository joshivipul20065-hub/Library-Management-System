<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ConflictException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'The request conflicts with the current state', string $errorCode = 'CONFLICT', array $details = [])
    {
        parent::__construct($message, 409, $errorCode, $details);
    }
}
