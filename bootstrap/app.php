<?php
use App\Core\Router;
use App\Core\DB;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
$dotenv = Dotenv::createImmutable($root);
$dotenv->safeLoad();

$app = [];
$app['router'] = new Router();

DB::init([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'hidden_gems',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? ''
]);

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

return $app;
