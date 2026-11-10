<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AuthorizationException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'You do not have permission to perform this action', string $errorCode = 'FORBIDDEN', array $details = [])
    {
        parent::__construct($message, 403, $errorCode, $details);
    }
}
