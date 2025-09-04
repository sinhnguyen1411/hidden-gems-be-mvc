<?php
namespace App\Http\Middleware;

class AdminMiddleware extends RoleMiddleware
{
    protected function roles(): array
    {
        return ['admin'];
    }
}
