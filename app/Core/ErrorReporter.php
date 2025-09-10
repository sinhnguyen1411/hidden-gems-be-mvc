<?php
namespace App\Core;

class ErrorReporter
{
    public static function report(\Throwable $e, Request $request, string $requestId): void
    {
        // Rollbar (simple server-side reporting)
        $token = $_ENV['ROLLBAR_ACCESS_TOKEN'] ?? '';
        if ($token !== '') {
            $payload = [
                'access_token' => $token,
                'data' => [
                    'environment' => $_ENV['APP_ENV'] ?? 'local',
                    'level' => 'error',
                    'timestamp' => time(),
                    'platform' => 'php',
                    'language' => 'php',
                    'notifier' => ['name'=>'hidden-gems-backend','version'=>'1.0'],
                    'body' => [
                        'trace' => [
                            'exception' => [
                                'class' => get_class($e),
                                'message' => $e->getMessage(),
                            ],
                        ]
                    ],
                    'request' => [
                        'url' => (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($request->getUri() ?? '/')),
                        'method' => $request->getMethod(),
                        'headers' => $request->headers ?? [],
                        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ],
                    'server' => [
                        'host' => gethostname(),
                    ],
                    'custom' => [
                        'request_id' => $requestId,
                    ],
                ]
            ];
            self::postJson('https://api.rollbar.com/api/1/item/', $payload);
        }

        // Sentry (placeholder): Use official SDK via composer for full features.
        // If SENTRY_DSN is set, we log a note; integrate SDK in production deployments.
        if (!empty($_ENV['SENTRY_DSN'])) {
            Logger::error('sentry_hint_configured', ['note'=>'Install sentry/sdk for full reporting']);
        }
    }

    private static function postJson(string $url, array $payload): void
    {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 2,
            ]
        ];
        try { @file_get_contents($url, false, stream_context_create($opts)); } catch (\Throwable $e) { /* ignore */ }
    }
}

