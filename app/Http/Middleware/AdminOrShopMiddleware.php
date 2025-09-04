<?php
namespace App\Http\Middleware;

class AdminOrShopMiddleware extends RoleMiddleware
{
    protected function roles(): array
    {
        return ['admin','shop'];
    }
}
