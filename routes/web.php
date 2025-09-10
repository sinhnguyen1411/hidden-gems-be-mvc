<?php
use App\Core\Router;
use App\Core\JsonResponse;

/** @var Router $router */
$router = $app['router'];

$router->add('GET','/',function($req){
    return JsonResponse::ok(['message'=>'Hidden Gems API']);
});

$router->add('GET','/health', function($req){
    return JsonResponse::ok(['status'=>'ok','time'=>date('c')]);
});

$router->add('GET','/ready', function($req){
    try {
        \App\Core\DB::pdo()->query('SELECT 1');
        return JsonResponse::ok(['status'=>'ready']);
    } catch (\Throwable $e) {
        return JsonResponse::ok(['status'=>'not_ready'], 500);
    }
});

$router->add('GET','/metrics', function($req){
    $text = \App\Core\Metrics::renderPrometheus();
    return (new \App\Core\Response())
        ->raw($text, 200, 'text/plain; version=0.0.4');
});
