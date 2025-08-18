<?php
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\CafeController;
use App\Controllers\ReviewController;
use App\Middlewares\AuthMiddleware;

/** @var Router $router */
$router = $app['router'];

$router->add('POST','/api/auth/register',[AuthController::class,'register']);
$router->add('POST','/api/auth/login',[AuthController::class,'login']);

$router->add('GET','/api/cafes',[CafeController::class,'index']);
$router->add('GET','/api/cafes/{id}',[CafeController::class,'show']);

$router->add('GET','/api/cafes/{id}/reviews',[ReviewController::class,'list']);
$router->add('POST','/api/cafes/{id}/reviews',[ReviewController::class,'create'], [AuthMiddleware::class]);