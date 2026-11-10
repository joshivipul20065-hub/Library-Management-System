<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ValidationException extends ApiException
{
    /** @param array<string, string> $fieldErrors field => message */
    public function __construct(string $message = 'The given data was invalid', array $fieldErrors = [])
    {
        parent::__construct($message, 422, 'VALIDATION_ERROR', $fieldErrors);
    }
}
