<?php

declare(strict_types=1);

namespace App\Validators;

use Closure;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/** Declarative definition of one input field: rule, error message, requiredness and type cast. */
final class Field
{
    private function __construct(
        public readonly string $label,
        public readonly bool $required,
        private readonly Validatable $rule,
        public readonly string $message,
        private readonly Closure $cast,
        private readonly ?Closure $prepare = null,
    ) {
    }

    public static function string(string $label, int $max, bool $required = true, int $min = 1): self
    {
        return new self(
            $label,
            $required,
            v::stringType()->length($min, $max),
            "{$label} must be between {$min} and {$max} characters",
            static fn (mixed $value): string => (string) $value,
        );
    }

    public static function text(string $label, string $pattern, string $message, int $max, bool $required = true): self
    {
        return new self(
            $label,
            $required,
            v::stringType()->length(1, $max)->regex($pattern),
            $message,
            static fn (mixed $value): string => (string) $value,
        );
    }

    public static function email(bool $required = true): self
    {
        return new self(
            'Email',
            $required,
            v::stringType()->email()->length(3, 190),
            'Enter a valid email address',
            static fn (mixed $value): string => strtolower((string) $value),
        );
    }

    public static function int(string $label, int $min, int $max, bool $required = true): self
    {
        return new self(
            $label,
            $required,
            v::intVal()->between($min, $max),
            "{$label} must be a whole number between {$min} and {$max}",
            static fn (mixed $value): int => (int) $value,
        );
    }

    public static function number(string $label, float $min, float $max, bool $required = true): self
    {
        return new self(
            $label,
            $required,
            v::numericVal()->between($min, $max),
            "{$label} must be a number between {$min} and {$max}",
            static fn (mixed $value): float => round((float) $value, 2),
        );
    }

    public static function date(string $label, bool $required = true): self
    {
        return new self(
            $label,
            $required,
            v::stringType()->date('Y-m-d'),
            "{$label} must be a valid date (YYYY-MM-DD)",
            static fn (mixed $value): string => (string) $value,
        );
    }

    /** @param list<string> $values */
    public static function in(string $label, array $values, bool $required = true): self
    {
        return new self(
            $label,
            $required,
            v::in($values, true),
            "{$label} must be one of: " . implode(', ', $values),
            static fn (mixed $value): string => (string) $value,
        );
    }

    public static function stringList(string $label, int $maxItems, int $maxLength, bool $required = false): self
    {
        return new self(
            $label,
            $required,
            v::arrayType()->length(0, $maxItems)->each(v::stringType()->length(1, $maxLength)),
            "{$label} must be a list of at most {$maxItems} values of up to {$maxLength} characters",
            static fn (mixed $value): array => array_values(array_unique(array_map(
                static fn (mixed $item): string => trim((string) $item),
                (array) $value
            ))),
            static fn (mixed $value): mixed => is_string($value)
                ? array_values(array_filter(
                    array_map('trim', preg_split('/[;|]/', $value) ?: []),
                    static fn (string $item): bool => $item !== ''
                ))
                : $value,
        );
    }

    public static function isbn(bool $required = true): self
    {
        return new self(
            'ISBN',
            $required,
            v::stringType()->regex('/^(\d{9}[\dX]|\d{13})$/'),
            'ISBN must be a valid 10 or 13 digit ISBN',
            static fn (mixed $value): string => (string) $value,
            static fn (mixed $value): mixed => is_string($value) ? strtoupper((string) preg_replace('/[\s-]/', '', $value)) : $value,
        );
    }

    public function prepare(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $this->prepare === null ? $value : ($this->prepare)($value);
    }

    public function isValid(mixed $value): bool
    {
        return $this->rule->validate($value);
    }

    public function cast(mixed $value): mixed
    {
        return ($this->cast)($value);
    }
}
