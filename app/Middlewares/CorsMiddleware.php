<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;

class CorsMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        return $request;
    }
}
