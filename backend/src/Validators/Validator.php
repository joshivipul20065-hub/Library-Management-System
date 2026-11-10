<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;

final class Validator
{
    /**
     * Validates $data against $fields and returns only the declared fields, trimmed and cast.
     * Optional fields that are missing or empty are returned as null.
     *
     * @param array<array-key, mixed> $data
     * @param array<string, Field> $fields
     * @return Row
     */
    public static function validate(array $data, array $fields): array
    {
        $errors = [];
        $clean = [];
        foreach ($fields as $name => $field) {
            $value = $field->prepare($data[$name] ?? null);
            if ($value === null || $value === '' || $value === []) {
                if ($field->required) {
                    $errors[$name] = "{$field->label} is required";
                } else {
                    $clean[$name] = null;
                }
                continue;
            }
            if (!$field->isValid($value)) {
                $errors[$name] = $field->message;
                continue;
            }
            $clean[$name] = $field->cast($value);
        }

        if ($errors !== []) {
            throw new ValidationException('The given data was invalid', $errors);
        }

        return $clean;
    }
}
