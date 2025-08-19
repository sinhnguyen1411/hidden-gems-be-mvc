<?php
namespace App\Core;

use App\Middlewares\CorsMiddleware;
use App\Core\Request;

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

    public function dispatch(Request $req, Response $res): void
    {
        foreach ($this->routes as $r) {
            if ($r['method'] !== $req->getMethod()) continue;
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $r['path']) . '$#';
            if (preg_match($pattern, $req->getUri(), $matches)) {
                foreach ($matches as $k=>$v) if (!is_int($k)) $req = $req->withAttribute($k,$v);
                $req = (new CorsMiddleware())->handle($req);
                foreach ($r['middlewares'] as $m) {
                    $req = (new $m())->handle($req);
                }
                if (is_array($r['handler'])) {
                    [$class,$method] = $r['handler'];
                    $controller = new $class();
                    $controller->$method($req,$res);
                } else {
                    call_user_func($r['handler'],$req,$res);
                }
                return;
            }
        }
        $res->json(['error'=>'Not found','path'=>$req->getUri()],404);
    }
}
