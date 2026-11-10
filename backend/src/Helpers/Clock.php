<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeZone;

/** Current time source; pass a fixed time in tests. */
class Clock
{
    public function __construct(private readonly ?string $fixed = null)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->fixed ?? 'now', new DateTimeZone('UTC'));
    }

    public function today(): DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }

    public function todayString(): string
    {
        return $this->today()->format('Y-m-d');
    }
}
