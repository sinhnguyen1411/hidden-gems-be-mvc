<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;

class CorsMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        $origin = $_ENV['CORS_ALLOWED_ORIGIN'] ?? '';
        $allowOrigin = $origin !== '' ? $origin : '*';
        header('Access-Control-Allow-Origin: ' . $allowOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        $csrfHeader = $_ENV['CSRF_HEADER_NAME'] ?? 'X-CSRF-Token';
        $allowHeaders = 'Content-Type, Authorization' . ($csrfHeader ? ', ' . $csrfHeader : '');
        header('Access-Control-Allow-Headers: ' . $allowHeaders);
        if (!empty($_ENV['CORS_ALLOW_CREDENTIALS'])) {
            header('Access-Control-Allow-Credentials: true');
        }
        if (!empty($_ENV['CORS_MAX_AGE'])) {
            header('Access-Control-Max-Age: ' . (int)$_ENV['CORS_MAX_AGE']);
        }
        return $request;
    }
}
