<?php
namespace App\Core;

class Mailer
{
    public static function send(string $toEmail, string $subject, string $html, ?string $text = null, ?string $toName = null): bool
    {
        $fromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'no-reply@example.com';
        $fromName  = $_ENV['MAIL_FROM_NAME'] ?? 'Hidden Gems';

        // SendGrid HTTP API (preferred if available)
        $sgKey = $_ENV['SENDGRID_API_KEY'] ?? '';
        if ($sgKey !== '') {
            $payload = [
                'personalizations' => [[ 'to' => [[ 'email' => $toEmail, 'name' => $toName ]] ]],
                'from' => [ 'email' => $fromEmail, 'name' => $fromName ],
                'subject' => $subject,
                'content' => array_values(array_filter([
                    $text ? ['type' => 'text/plain', 'value' => $text] : null,
                    ['type' => 'text/html', 'value' => $html]
                ]))
            ];
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $sgKey
                    ],
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'timeout' => 5,
                ]
            ];
            $resp = @file_get_contents('https://api.sendgrid.com/v3/mail/send', false, stream_context_create($opts));
            if ($resp !== false) { return true; }
        }

        // Fallback: log as info for development
        Logger::info('mail_fallback', [
            'to' => $toEmail,
            'subject' => $subject,
        ]);
        return true;
    }
}

