<?php

declare(strict_types=1);

namespace App\Repositories;

interface SettingRepositoryInterface
{
    /** @return array<string, string> */
    public function all(): array;

    public function set(string $name, string $value): void;
}
