<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth as JWTAuth;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request = null): void
    {
        $hdr = $request->headers['Authorization'] ?? '';
        if (!preg_match('#^Bearer\s+(.*)$#i', $hdr, $m)) {
            http_response_code(401);
            echo json_encode(['error'=>'Missing token']);
            exit;
        }
        try {
            $claims = JWTAuth::verify($m[1]);
            $request->user = $claims;
        } catch (\Throwable $e) {
            http_response_code(401);
            echo json_encode(['error'=>'Invalid token']);
            exit;
        }
    }
}
