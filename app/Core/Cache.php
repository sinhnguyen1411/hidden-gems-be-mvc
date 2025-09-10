<?php
namespace App\Core;

class Cache
{
    private static bool $tried = false;
    private static $redis = null; // Predis\Client|null
    private static string $dir;
    private static array $dbgHits = [];
    private static array $dbgMisses = [];

    private static function init(): void
    {
        if (self::$tried) return;
        self::$tried = true;
        self::$dir = dirname(__DIR__,2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir(self::$dir)) @mkdir(self::$dir, 0775, true);

        $url = $_ENV['REDIS_URL'] ?? '';
        $host = $_ENV['REDIS_HOST'] ?? '';
        try {
            if ($url !== '') {
                self::$redis = new \Predis\Client($url);
                self::$redis->ping();
            } elseif ($host !== '') {
                $params = [
                    'scheme' => 'tcp',
                    'host' => $host,
                    'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                    'password' => $_ENV['REDIS_PASSWORD'] ?? null,
                ];
                self::$redis = new \Predis\Client($params);
                self::$redis->ping();
            }
        } catch (\Throwable $e) {
            self::$redis = null; // fallback to file cache
        }
    }

    public static function get(string $key)
    {
        self::init();
        if (self::$redis) {
            $raw = self::$redis->get($key);
            if ($raw === null) { self::$dbgMisses[] = $key; return null; }
            self::$dbgHits[] = $key;
            return json_decode($raw, true);
        }
        $path = self::$dir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
        if (!is_file($path)) { self::$dbgMisses[] = $key; return null; }
        $json = @file_get_contents($path);
        $data = json_decode($json, true);
        if (!is_array($data)) { self::$dbgMisses[] = $key; return null; }
        if (isset($data['exp']) && time() > (int)$data['exp']) { @unlink($path); self::$dbgMisses[] = $key; return null; }
        self::$dbgHits[] = $key;
        return $data['val'] ?? null;
    }

    public static function set(string $key, $value, int $ttl): void
    {
        self::init();
        if (self::$redis) {
            $payload = json_encode($value);
            self::$redis->setex($key, $ttl, $payload);
            return;
        }
        $path = self::$dir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
        $data = ['val' => $value, 'exp' => time() + $ttl];
        @file_put_contents($path, json_encode($data), LOCK_EX);
    }

    public static function remember(string $key, int $ttl, callable $callback)
    {
        $val = self::get($key);
        if ($val !== null) return $val;
        $val = $callback();
        if ($val !== null) self::set($key, $val, $ttl);
        return $val;
    }

    // Debug helpers (per-request)
    public static function resetDebug(): void
    {
        self::$dbgHits = [];
        self::$dbgMisses = [];
    }

    public static function debugSummary(): array
    {
        // Unique keys to avoid duplicates if get() called multiple times
        return [
            'hits' => array_values(array_unique(self::$dbgHits)),
            'misses' => array_values(array_unique(self::$dbgMisses)),
        ];
    }
}
