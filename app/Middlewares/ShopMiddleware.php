<?php
namespace App\Middlewares;

class ShopMiddleware extends RoleMiddleware
{
    protected function roles(): array
    {
        return ['shop'];
    }
}
