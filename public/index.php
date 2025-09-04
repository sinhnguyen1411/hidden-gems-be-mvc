<?php
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;
use App\Http\Middleware\CorsMiddleware;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$request = Request::capture();
// Ensure CORS headers are present even if dispatch/errors happen later
(new CorsMiddleware())->handle($request);

try {
    $response = $app['router']->dispatch($request);
} catch (HttpException $e) {
    $response = (new Response())->json(['error' => $e->getMessage()], $e->getStatus());
} catch (Throwable $e) {
    error_log($e->__toString());
    $response = (new Response())->json(['error' => 'Server error'], 500);
}

$response->send();
