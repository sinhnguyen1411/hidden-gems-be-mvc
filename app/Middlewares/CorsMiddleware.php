<?php
namespace App\Middlewares;

use App\Core\Middleware;

class CorsMiddleware implements Middleware
{
    public function handle($request = null): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}
