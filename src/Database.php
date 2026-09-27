<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * DB接続。SQLは必ずプリペアドステートメント（? や :name）で値を渡す。
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', 'localhost'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_NAME', ''),
            );
            self::$pdo = new PDO($dsn, Config::get('DB_USER', ''), Config::get('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+09:00'");
        }
        return self::$pdo;
    }
}
