<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\SettingRepositoryInterface;
use App\Validators\Field;
use App\Validators\Validator;

/** Circulation rules: values stored in the settings table override the .env defaults. */
class SettingsService
{
    /** name => [env variable, default, min, max, decimal] */
    public const DEFINITIONS = [
        'loan_days' => ['LOAN_DAYS', 14, 1, 365, false],
        'max_books_per_student' => ['MAX_BOOKS_PER_STUDENT', 5, 1, 50, false],
        'max_renewals' => ['MAX_RENEWALS', 3, 0, 20, false],
        'fine_per_day' => ['FINE_PER_DAY', 5, 0, 10000, true],
        'reservation_hold_hours' => ['RESERVATION_HOLD_HOURS', 48, 1, 720, false],
        'lost_book_fee' => ['LOST_BOOK_FEE', 500, 0, 100000, true],
    ];

    /** @var array<string, int|float>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string, int|float> */
    public function all(): array
    {
        if ($this->cache === null) {
            $stored = $this->settings->all();
            $this->cache = [];
            foreach (self::DEFINITIONS as $name => [$env, $default, , , $decimal]) {
                $raw = $stored[$name] ?? Env::get($env) ?? (string) $default;
                $this->cache[$name] = $decimal ? round((float) $raw, 2) : (int) $raw;
            }
        }

        return $this->cache;
    }

    public function int(string $name): int
    {
        return (int) $this->all()[$name];
    }

    public function float(string $name): float
    {
        return (float) $this->all()[$name];
    }

    /**
     * @param Row $input
     * @return array<string, int|float>
     */
    public function update(array $input): array
    {
        $fields = [];
        foreach (self::DEFINITIONS as $name => [, , $min, $max, $decimal]) {
            $label = ucfirst(str_replace('_', ' ', $name));
            $fields[$name] = $decimal ? Field::number($label, $min, $max, false) : Field::int($label, $min, $max, false);
        }
        $changes = array_filter(Validator::validate($input, $fields), static fn (mixed $value): bool => $value !== null);
        foreach ($changes as $name => $value) {
            $this->settings->set($name, (string) $value);
        }
        $this->cache = null;
        $this->audit->log('settings.update', 'settings', null, $changes);

        return $this->all();
    }
}
