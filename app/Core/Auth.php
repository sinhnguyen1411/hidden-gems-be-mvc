<?php
namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    public static function issue(array $payload, int $ttlSeconds = 3600): string
    {
        $now = time();
        $payload = array_merge($payload,[
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);
        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    public static function verify(string $token): array
    {
        return (array)JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
    }
}
