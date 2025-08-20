<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth as JWTAuth;
use App\Core\HttpException;
use Firebase\JWT\ExpiredException;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        $hdr = $request->getHeaderLine('Authorization');
        if (!preg_match('#^Bearer\s+(.*)$#i', $hdr, $m)) {
            throw new HttpException('Missing token', 401);
        }
        try {
            $claims = JWTAuth::verify($m[1]);
            return $request->withAttribute('user',$claims);
        } catch (ExpiredException $e) {
            error_log($e->getMessage());
            throw new HttpException('Token expired', 401);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            throw new HttpException('Invalid token', 401);
        }
    }
}
