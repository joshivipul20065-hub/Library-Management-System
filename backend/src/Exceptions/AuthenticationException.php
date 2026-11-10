<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AuthenticationException extends ApiException
{
    /** @param array<string, mixed> $details */
    public function __construct(string $message = 'Authentication required', string $errorCode = 'UNAUTHENTICATED', array $details = [])
    {
        parent::__construct($message, 401, $errorCode, $details);
    }
}
