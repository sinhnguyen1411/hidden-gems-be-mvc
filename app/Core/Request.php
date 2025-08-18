<?php
namespace App\Core;

class Request
{
    public string $method;
    public string $uri;
    public array $headers;
    public array $query;
    public array $body;
    public array $params = [];
    public array $user = [];

    public static function capture(): self
    {
        $req = new self();
        $req->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $req->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $req->headers = function_exists('getallheaders') ? getallheaders() : [];
        $req->query = $_GET ?? [];
        $raw = file_get_contents('php://input');
        $req->body = json_decode($raw, true) ?: [];
        return $req;
    }
}
