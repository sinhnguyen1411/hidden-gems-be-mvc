<?php
namespace App\Core;

class Request
{
    public string $method;
    public string $uri;
    public array $headers;
    public array $query;
    public array $body;
    public array $files = [];
    private array $attributes = [];
    private bool $jsonError = false;

    public static function capture(): self
    {
        $req = new self();
        $req->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($uri === '/index.php' || str_starts_with($uri, '/index.php/')) {
            $uri = substr($uri, strlen('/index.php')) ?: '/';
        }
        // Normalize trailing slash (except root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }
        $req->uri = $uri;

        // Normalize headers to lowercase keys; add common fallbacks
        $rawHeaders = function_exists('getallheaders') ? (array)getallheaders() : [];
        $headers = [];
        foreach ($rawHeaders as $k => $v) { $headers[strtolower($k)] = $v; }
        if (!isset($headers['authorization'])) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
            elseif (isset($_SERVER['Authorization'])) $headers['authorization'] = $_SERVER['Authorization'];
        }
        if (!isset($headers['content-type']) && isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        $req->headers = $headers;
        $req->query = $_GET ?? [];

        $contentType = $headers['content-type'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if ($raw !== '' && $data === null && json_last_error() !== JSON_ERROR_NONE) {
                $req->jsonError = true;
                $req->body = [];
            } else {
                $req->body = $data ?: [];
            }
        } else {
            // Fallback to form-encoded or multipart form data
            $req->body = $_POST ?? [];
            $req->files = $_FILES ?? [];
        }
        return $req;
    }

    // PSR-7 like helpers
    public function getMethod(): string { return $this->method; }
    public function getUri(): string { return $this->uri; }
    public function getHeaderLine(string $name): string { $key = strtolower($name); return $this->headers[$key] ?? ''; }
    public function getQueryParams(): array { return $this->query; }
    public function getParsedBody(): array { return $this->body; }
    public function getUploadedFiles(): array { return $this->files; }
    public function withAttribute(string $key, $value): self { $clone = clone $this; $clone->attributes[$key] = $value; return $clone; }
    public function getAttribute(string $key, $default=null) { return $this->attributes[$key] ?? $default; }

    public function isJson(): bool
    {
        return stripos($this->getHeaderLine('content-type'), 'application/json') !== false;
    }

    public function hasJsonError(): bool
    {
        return $this->jsonError;
    }

    // Simple filtering helpers
    public function getString(string $key, string $source = 'body', int $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS): ?string
    {
        $value = $this->getValue($key, $source);
        if ($value === null) return null;
        $val = filter_var((string)$value, $filter, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $val;
    }

    public function getInt(string $key, string $source = 'body'): ?int
    {
        $value = $this->getValue($key, $source);
        if ($value === null) return null;
        $opt = ['options' => ['min_range' => PHP_INT_MIN, 'max_range' => PHP_INT_MAX]];
        $val = filter_var($value, FILTER_VALIDATE_INT, $opt);
        return $val === false ? null : (int)$val;
    }

    public function getBool(string $key, string $source = 'body'): ?bool
    {
        $value = $this->getValue($key, $source);
        if ($value === null) return null;
        $val = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $val;
    }

    public function sanitizeArray(array $data, array $map = []): array
    {
        $out = [];
        foreach ($data as $k=>$v) {
            if (is_array($v)) { $out[$k] = $this->sanitizeArray($v, $map[$k] ?? []); continue; }
            $filter = $map[$k] ?? FILTER_SANITIZE_FULL_SPECIAL_CHARS;
            $out[$k] = filter_var((string)$v, $filter, FILTER_FLAG_NO_ENCODE_QUOTES);
        }
        return $out;
    }

    private function getValue(string $key, string $source)
    {
        return match ($source) {
            'query' => $this->query[$key] ?? null,
            'body' => $this->body[$key] ?? null,
            'attr' => $this->getAttribute($key),
            'header' => $this->getHeaderLine($key),
            default => $this->body[$key] ?? null
        };
    }
}
