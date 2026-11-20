<?php

namespace App\Repositories;

class SettingsRepository extends BaseRepository
{
    /** @return array<string, string|null> */
    public function all(): array
    {
        return $this->run('SELECT setting_key, setting_value FROM showroom_settings')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function save(array $values): void
    {
        $stmt = $this->db->prepare('INSERT INTO showroom_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $k => $v) {
            $stmt->execute([$k, $v === null ? null : (string) $v]);
        }
    }
}
