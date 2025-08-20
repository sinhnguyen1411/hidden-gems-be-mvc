<?php
namespace App\Core;

class Request
{
    public string $method;
    public string $uri;
    public array $headers;
    public array $query;
    public array $body;
    private array $attributes = [];

    public static function capture(): self
    {
        $req = new self();
        $req->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($uri === '/index.php' || str_starts_with($uri, '/index.php/')) {
            $uri = substr($uri, strlen('/index.php')) ?: '/';
        }
        $req->uri = $uri;
        $req->headers = function_exists('getallheaders') ? getallheaders() : [];
        $req->query = $_GET ?? [];
        $raw = file_get_contents('php://input');
        $req->body = json_decode($raw, true) ?: [];
        return $req;
    }

    // PSR-7 like helpers
    public function getMethod(): string { return $this->method; }
    public function getUri(): string { return $this->uri; }
    public function getHeaderLine(string $name): string { return $this->headers[$name] ?? ''; }
    public function getQueryParams(): array { return $this->query; }
    public function getParsedBody(): array { return $this->body; }
    public function withAttribute(string $key, $value): self { $clone = clone $this; $clone->attributes[$key] = $value; return $clone; }
    public function getAttribute(string $key, $default=null) { return $this->attributes[$key] ?? $default; }
}
