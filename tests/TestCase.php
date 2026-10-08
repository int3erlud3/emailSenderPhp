<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\Config;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param array<string, string> $overrides
     */
    protected static function config(array $overrides = []): Config
    {
        return Config::fromEnv(array_merge([
            'SMTP_HOST' => 'smtp.example.test',
            'SMTP_PORT' => '587',
            'SMTP_ENCRYPTION' => 'starttls',
            'SMTP_USERNAME' => 'mailer',
            'SMTP_PASSWORD' => 'test-only-not-a-secret',
            'MAIL_FROM' => 'noreply@example.test',
            'MAIL_FROM_NAME' => 'Website',
            'MAIL_TO' => 'inbox@example.test',
            'RATE_LIMIT_DIR' => sys_get_temp_dir() . '/email-sender-tests-' . getmypid(),
        ], $overrides));
    }

    protected static function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/email-sender-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o700);
        return $dir;
    }
}
