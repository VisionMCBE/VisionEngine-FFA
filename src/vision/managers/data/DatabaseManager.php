<?php

declare(strict_types=1);

namespace vision\managers\data;

use PDO;
use pocketmine\utils\Config;
use vision\Main;

final class DatabaseManager {
    private static ?PDO $database = null;
    private static ?Main $plugin = null;

    public static function init(Main $plugin): void
    {
        self::$plugin = $plugin;
    }

    public static function get(): PDO
    {
        if (self::$database !== null) {
            return self::$database;
        }

        $plugin = self::$plugin ?? Main::getInstance();
        $path = is_file($plugin->getDataFolder() . 'database.json')
            ? $plugin->getDataFolder() . 'database.json'
            : $plugin->getDataFolder() . 'database.json';
        $config = new Config($path, Config::JSON);
        $profiles = $config->get('profiles', null);
        $settings = is_array($profiles) ? ($profiles['main'] ?? []) : (array) $config->get('main', []);
        $driver = strtolower((string) ($settings['driver'] ?? 'sqlite'));

        if ($driver === 'mysql') {
            $host = (string) ($settings['host'] ?? '127.0.0.1');
            $port = (int) ($settings['port'] ?? 3306);
            $database = (string) ($settings['database'] ?? 'vision');
            $charset = (string) ($settings['charset'] ?? 'utf8mb4');
            self::$database = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
                (string) ($settings['username'] ?? 'root'),
                (string) ($settings['password'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            return self::$database;
        }

        $path = (string) ($settings['path'] ?? 'storage.sqlite');
        if (!str_contains($path, ':') && !str_starts_with($path, '/')) {
            $path = $plugin->getDataFolder() . $path;
        }
        self::$database = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$database;
    }

    public static function isSqlite(PDO $database): bool
    {
        return strtolower((string) $database->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite';
    }

    public static function close(): void
    {
        self::$database = null;
    }
}
