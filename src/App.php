<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Wiring of the web front end from environment variables.
 */
final class App
{
    public static function config(): Config
    {
        /** @var array<string, string> $env */
        $env = getenv();
        return Config::fromEnv($env);
    }

    public static function contactHandler(Config $config): ContactHandler
    {
        return new ContactHandler(
            new Mailer($config),
            new RateLimiter($config->rateLimitDir, $config->rateLimitMax, $config->rateLimitWindow),
            $config->mailTo,
        );
    }
}
