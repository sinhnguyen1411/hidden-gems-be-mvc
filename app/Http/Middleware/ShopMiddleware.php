<?php
namespace App\Http\Middleware;

class ShopMiddleware extends RoleMiddleware
{
    protected function roles(): array
    {
        return ['shop'];
    }
}
