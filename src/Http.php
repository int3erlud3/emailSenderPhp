<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * HTTP helpers for the web front end: security headers and hardened sessions.
 */
final class Http
{
    public const CSP = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; "
        . "connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";

    /**
     * @return array<string, string>
     */
    public static function securityHeaders(): array
    {
        return [
            'Content-Security-Policy' => self::CSP,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
        ];
    }

    public static function sendSecurityHeaders(): void
    {
        header_remove('X-Powered-By');
        foreach (self::securityHeaders() as $name => $value) {
            header("$name: $value");
        }
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('contact_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    /**
     * The client address used for rate limiting. Only REMOTE_ADDR is trusted; behind a
     * reverse proxy, configure the proxy (e.g. mod_remoteip / real_ip) to set it.
     */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
