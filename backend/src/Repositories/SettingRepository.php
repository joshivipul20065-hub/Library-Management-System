<?php

declare(strict_types=1);

namespace App\Repositories;

final class SettingRepository extends BaseRepository implements SettingRepositoryInterface
{
    public function all(): array
    {
        $settings = [];
        foreach ($this->db->all('SELECT name, value FROM settings') as $row) {
            $settings[(string) $row['name']] = (string) $row['value'];
        }

        return $settings;
    }

    public function set(string $name, string $value): void
    {
        $this->db->execute(
            'INSERT INTO settings (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            ['name' => $name, 'value' => $value]
        );
    }
}
