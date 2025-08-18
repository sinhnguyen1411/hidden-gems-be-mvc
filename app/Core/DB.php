<?php
namespace App\Core;

use PDO;
use PDOException;

class DB
{
    private static PDO $pdo;

    public static function init(array $cfg): void
    {
        $driver = $cfg['driver'] ?? 'mysql';
        $host   = $cfg['host'] ?? '127.0.0.1';
        $port   = (int)($cfg['port'] ?? 3306);
        $db     = $cfg['database'] ?? 'hidden_gems';
        $user   = $cfg['username'] ?? 'root';
        $pass   = $cfg['password'] ?? '';
        $charset = 'utf8mb4';

        try {
            $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $db, $charset);
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown database')) {
                $dsnNoDb = sprintf('%s:host=%s;port=%d;charset=%s', $driver, $host, $port, $charset);
                $tmp = new PDO($dsnNoDb, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                $tmp = null;

                $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $db, $charset);
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                return;
            }
            throw $e;
        }
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}
