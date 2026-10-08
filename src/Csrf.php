<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Synchronizer-token CSRF protection stored in the session. The token is rotated after
 * each successful validation; the form timestamp lets the handler reject instant bot posts.
 */
final class Csrf
{
    private const KEY = '_csrf_token';
    private const TIME_KEY = '_csrf_issued';

    /**
     * @param array<string, mixed> $session
     */
    public static function token(array &$session, ?int $now = null): string
    {
        if (!isset($session[self::KEY]) || !is_string($session[self::KEY])) {
            $session[self::KEY] = bin2hex(random_bytes(32));
            $session[self::TIME_KEY] = $now ?? time();
        }
        return $session[self::KEY];
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function validate(array &$session, mixed $submitted): bool
    {
        $expected = $session[self::KEY] ?? null;
        if (!is_string($expected) || !is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function issuedAt(array $session): ?int
    {
        $t = $session[self::TIME_KEY] ?? null;
        return is_int($t) ? $t : null;
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function rotate(array &$session): void
    {
        unset($session[self::KEY], $session[self::TIME_KEY]);
    }
}
