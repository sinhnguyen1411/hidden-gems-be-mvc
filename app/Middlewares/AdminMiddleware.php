<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;

class AdminMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        $user = $request->getAttribute('user', []);
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error'=>'Forbidden']);
            exit;
        }
        return $request;
    }
}
