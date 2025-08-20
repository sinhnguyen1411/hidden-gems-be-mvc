<?php
namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\HttpException;

abstract class RoleMiddleware implements Middleware
{
    /**
     * Return allowed roles for the middleware.
     *
     * @return string[]
     */
    abstract protected function roles(): array;

    public function handle(Request $request): Request
    {
        $user = $request->getAttribute('user', []);
        if (!in_array($user['role'] ?? '', $this->roles(), true)) {
            throw new HttpException('Forbidden', 403);
        }
        return $request;
    }
}
