<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Csrf;

class CsrfController
{
    public function token(Request $req): Response
    {
        $issued = Csrf::issue();
        $cookieName = $_ENV['CSRF_COOKIE_NAME'] ?? 'XSRF-TOKEN';
        $ttl = (int)($_ENV['CSRF_LIFETIME_SECONDS'] ?? 7200);
        $secure = isset($_ENV['APP_URL']) && str_starts_with($_ENV['APP_URL'], 'https');
        setcookie($cookieName, $issued['token'], [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false, // readable by JS for SPA header usage
            'samesite' => 'Lax'
        ]);
        return (new Response())->json(['token' => $issued['token'], 'expires_at' => $issued['expires_at']]);
    }
}

