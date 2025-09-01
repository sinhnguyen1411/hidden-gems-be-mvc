<?php
namespace App\Core;

class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public function json($data, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    public function jsonError(string $message, int $status = 400, ?array $details = null): self
    {
        $payload = ['error' => $message];
        if ($details) { $payload['details'] = $details; }
        return $this->json($payload, $status);
    }

    public function paginated(array $items, int $total, int $page, int $per): self
    {
        return $this->json([
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per]
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
            return; // No body for HEAD
        }
        echo $this->body;
    }
}
