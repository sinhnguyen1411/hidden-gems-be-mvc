<?php
namespace App\Core;

use App\Middlewares\CorsMiddleware;
use App\Core\Request;
use App\Core\Response;

class Router
{
    private array $routes = [];

    public function __construct()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            (new CorsMiddleware())->handle(Request::capture());
            http_response_code(204);
            exit;
        }
    }

    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->routes[] = compact('method','path','handler','middlewares');
    }

    public function dispatch(Request $req): Response
    {
        // Ensure CORS headers for all responses
        $req = (new CorsMiddleware())->handle($req);

        $method = $req->getMethod();
        $matchMethod = ($method === 'HEAD') ? 'GET' : $method;
        $allowedForPath = [];

        foreach ($this->routes as $r) {
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $r['path']) . '$#';
            if (preg_match($pattern, $req->getUri())) {
                $allowedForPath[] = $r['method'];
            }
        }

        foreach ($this->routes as $r) {
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $r['path']) . '$#';
            if (!preg_match($pattern, $req->getUri(), $matches)) {
                continue;
            }
            if ($r['method'] !== $matchMethod) {
                continue;
            }
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $req = $req->withAttribute($k, $v);
                }
            }

            // If client sent invalid JSON with JSON content-type, return 400
            if ($req->isJson() && $req->hasJsonError()) {
                return (new Response())->jsonError('Invalid JSON', 400);
            }

            foreach ($r['middlewares'] as $m) {
                $req = (new $m())->handle($req);
            }
            if (is_array($r['handler'])) {
                [$class, $method] = $r['handler'];
                $controller = new $class();
                return $controller->$method($req);
            }
            return call_user_func($r['handler'], $req);
        }

        if (!empty($allowedForPath)) {
            return (new Response())
                ->withHeader('Allow', implode(', ', array_unique($allowedForPath)))
                ->jsonError('Method Not Allowed', 405);
        }
        return (new Response())->jsonError('Not found', 404);
    }
}
