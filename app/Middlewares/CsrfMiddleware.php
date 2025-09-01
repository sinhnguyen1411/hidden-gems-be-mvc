<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\HttpException;
use App\Security\Csrf;

class CsrfMiddleware implements Middleware
{
    private array $methods = ['POST','PUT','PATCH','DELETE'];

    public function handle(Request $request): Request
    {
        if (empty($_ENV['CSRF_ENABLED'])) return $request;
        $m = strtoupper($request->getMethod());
        if (!in_array($m, $this->methods, true)) return $request;

        $headerName = $_ENV['CSRF_HEADER_NAME'] ?? 'X-CSRF-Token';
        $cookieName = $_ENV['CSRF_COOKIE_NAME'] ?? 'XSRF-TOKEN';
        $token = $request->getHeaderLine($headerName);
        if ($token === '' && isset($_COOKIE[$cookieName])) {
            $token = $_COOKIE[$cookieName];
        }
        if ($token === '' && isset($request->getParsedBody()['csrf_token'])) {
            $token = (string)$request->getParsedBody()['csrf_token'];
        }
        if ($token === '' || !Csrf::verify($token)) {
            throw new HttpException('CSRF token invalid or missing', 403);
        }
        return $request;
    }
}

