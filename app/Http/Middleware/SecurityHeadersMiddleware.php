<?php
namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;

class SecurityHeadersMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        // HSTS (only meaningful on HTTPS)
        $hstsMaxAge = (int)($_ENV['SECURITY_HSTS_MAX_AGE'] ?? 15552000); // 180 days
        if ($hstsMaxAge > 0) {
            $hsts = 'max-age=' . $hstsMaxAge;
            if (!empty($_ENV['SECURITY_HSTS_SUBDOMAINS'])) { $hsts .= '; includeSubDomains'; }
            if (!empty($_ENV['SECURITY_HSTS_PRELOAD'])) { $hsts .= '; preload'; }
            header('Strict-Transport-Security: ' . $hsts);
        }

        // Basic hardening
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');

        // Permissions-Policy (conservative defaults)
        $pp = $_ENV['SECURITY_PERMISSIONS_POLICY'] ?? 'geolocation=(), microphone=(), camera=()';
        if ($pp !== '') header('Permissions-Policy: ' . $pp);

        // Content-Security-Policy (configurable, conservative default)
        $csp = $_ENV['SECURITY_CSP'] ?? "default-src 'self'; img-src 'self' data: https:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'";
        if ($csp !== '') header('Content-Security-Policy: ' . $csp);
        return $request;
    }
}
