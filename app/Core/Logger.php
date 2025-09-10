<?php
namespace App\Core;

class Logger
{
    private static ?string $channel = null; // 'stdout' | 'file'
    private static ?string $path = null;
    private static array $base = [];

    public static function init(): void
    {
        if (self::$channel !== null) return;
        self::$channel = $_ENV['LOG_CHANNEL'] ?? 'stdout';
        self::$path = $_ENV['LOG_PATH'] ?? (dirname(__DIR__,2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app.log');
        self::$base = [
            'app' => 'hidden-gems',
            'env' => $_ENV['APP_ENV'] ?? 'local',
        ];
    }

    public static function withBase(array $ctx): void
    {
        self::init();
        self::$base = array_merge(self::$base, $ctx);
    }

    public static function info(string $message, array $ctx = []): void
    {
        self::write('info', $message, $ctx);
    }

    public static function error(string $message, array $ctx = []): void
    {
        self::write('error', $message, $ctx);
    }

    public static function request(array $ctx): void
    {
        self::write('request', 'http_request', $ctx);
    }

    private static function write(string $level, string $message, array $ctx): void
    {
        self::init();
        $record = array_merge(self::$base, $ctx, [
            'level' => $level,
            'message' => $message,
            'ts' => date('c'),
        ]);
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if (self::$channel === 'file') {
            $dir = dirname(self::$path);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            file_put_contents(self::$path, $line, FILE_APPEND | LOCK_EX);
        } else {
            // stderr (avoid corrupting HTTP response body)
            error_log(rtrim($line));
        }
    }
}
