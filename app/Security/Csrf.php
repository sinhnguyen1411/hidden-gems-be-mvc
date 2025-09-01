<?php
namespace App\Security;

class Csrf
{
    public static function issue(int $ttlSeconds = null): array
    {
        $ttl = $ttlSeconds ?? (int)($_ENV['CSRF_LIFETIME_SECONDS'] ?? 7200);
        $exp = time() + $ttl;
        $nonce = bin2hex(random_bytes(16));
        $payload = $nonce . '|' . $exp;
        $secret = $_ENV['CSRF_SECRET'] ?? ($_ENV['JWT_SECRET'] ?? 'secret');
        $sig = hash_hmac('sha256', $payload, $secret);
        $token = base64_encode($payload . '|' . $sig);
        return ['token' => $token, 'expires_at' => $exp];
    }

    public static function verify(string $token): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) return false;
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) return false;
        [$nonce, $exp, $sig] = $parts;
        if ((int)$exp < time()) return false;
        $payload = $nonce . '|' . $exp;
        $secret = $_ENV['CSRF_SECRET'] ?? ($_ENV['JWT_SECRET'] ?? 'secret');
        $calc = hash_hmac('sha256', $payload, $secret);
        return hash_equals($calc, $sig);
    }
}

