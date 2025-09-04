<?php
use App\Core\Router;
use App\Core\JsonResponse;

/** @var Router $router */
$router = $app['router'];

$router->add('GET','/',function($req){
    return JsonResponse::ok(['message'=>'Hidden Gems API']);
});

