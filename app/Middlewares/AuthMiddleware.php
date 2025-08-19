<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth as JWTAuth;
use Firebase\JWT\ExpiredException;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        $hdr = $request->getHeaderLine('Authorization');
        if (!preg_match('#^Bearer\s+(.*)$#i', $hdr, $m)) {
            http_response_code(401);
            echo json_encode(['error'=>'Missing token']);
            exit;
        }
        try {
            $claims = JWTAuth::verify($m[1]);
            return $request->withAttribute('user',$claims);
        } catch (ExpiredException $e) {
            error_log($e->getMessage());
            http_response_code(401);
            echo json_encode(['error'=>'Token expired']);
            exit;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(401);
            echo json_encode(['error'=>'Invalid token']);
            exit;
        }
    }
}
