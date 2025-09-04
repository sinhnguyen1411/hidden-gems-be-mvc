<?php
namespace App\Core;

use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;

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
        // Ensure security and CORS headers for all responses
        $req = (new SecurityHeadersMiddleware())->handle($req);
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
                return JsonResponse::error('Invalid JSON', 400);
            }

            // Enforce CSRF for state-changing methods if enabled
            $req = (new CsrfMiddleware())->handle($req);

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
            return JsonResponse::error('Method Not Allowed', 405)
                ->withHeader('Allow', implode(', ', array_unique($allowedForPath)));
        }
        return JsonResponse::error('Not found', 404);
    }
}
