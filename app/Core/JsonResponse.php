<?php
namespace App\Core;

class JsonResponse extends Response
{
    public static function ok($data = [], int $status = 200): self
    {
        return (new self())->json($data, $status);
    }

    public static function error(string $message, int $status = 400, ?array $details = null): self
    {
        return (new self())->jsonError($message, $status, $details);
    }
}
