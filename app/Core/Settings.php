<?php
declare(strict_types=1);
namespace App\Core;

final class Settings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $s = Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $v === false ? $default : (string)$v;
    }

    public static function set(string $key, string $value): void
    {
        Database::pdo()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
    }
}
