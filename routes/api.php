<?php
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\CafeController;
use App\Controllers\ReviewController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Core\Response;

/** @var Router $router */
$router = $app['router'];

$router->add('GET','/',function($req){
    return (new Response())->json(['message'=>'Hidden Gems API']);
});

$router->add('POST','/api/auth/register',[AuthController::class,'register']);
$router->add('POST','/api/auth/login',[AuthController::class,'login']);
$router->add('POST','/api/auth/refresh',[AuthController::class,'refresh']);
$router->add('GET','/api/users',[AuthController::class,'users'],[AuthMiddleware::class,AdminMiddleware::class]);

$router->add('GET','/api/cafes',[CafeController::class,'index']);
$router->add('GET','/api/cafes/search',[CafeController::class,'search']);
$router->add('GET','/api/cafes/{id}',[CafeController::class,'show']);

$router->add('GET','/api/cafes/{id}/reviews',[ReviewController::class,'list']);
$router->add('POST','/api/cafes/{id}/reviews',[ReviewController::class,'create'], [AuthMiddleware::class]);
