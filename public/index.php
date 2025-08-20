<?php
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$request = Request::capture();
$response = new Response();

try {
    $app['router']->dispatch($request, $response);
} catch (HttpException $e) {
    http_response_code($e->getStatus());
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
