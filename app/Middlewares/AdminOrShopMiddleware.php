<?php
namespace App\Middlewares;

class AdminOrShopMiddleware extends RoleMiddleware
{
    protected function roles(): array
    {
        return ['admin','shop'];
    }
}

