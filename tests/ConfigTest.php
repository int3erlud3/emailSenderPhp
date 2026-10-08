<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\Config;
use EmailSender\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConfigTest extends TestCase
{
    public function testDefaultsToStarttlsOnPort587(): void
    {
        $c = self::config(['SMTP_PORT' => '']);
        self::assertSame(Config::ENCRYPTION_STARTTLS, $c->encryption);
        self::assertSame(587, $c->smtpPort);
        self::assertSame(465, self::config(['SMTP_PORT' => '', 'SMTP_ENCRYPTION' => 'smtps'])->smtpPort);
    }

    public function testPlaintextRequiresExplicitOptIn(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/SMTP_ALLOW_INSECURE/');
        self::config(['SMTP_ENCRYPTION' => 'none', 'SMTP_USERNAME' => '', 'SMTP_PASSWORD' => '']);
    }

    public function testPlaintextAllowedForLocalTestServerWithoutCredentials(): void
    {
        $c = self::config([
            'SMTP_HOST' => 'mailpit', 'SMTP_PORT' => '1025', 'SMTP_ENCRYPTION' => 'none',
            'SMTP_ALLOW_INSECURE' => '1', 'SMTP_USERNAME' => '', 'SMTP_PASSWORD' => '',
        ]);
        self::assertSame(Config::ENCRYPTION_NONE, $c->encryption);
    }

    public function testCredentialsAreNeverSentWithoutTls(): void
    {
        $this->expectExceptionMessageMatches('/without TLS/');
        self::config(['SMTP_ENCRYPTION' => 'none', 'SMTP_ALLOW_INSECURE' => '1']);
    }

    public function testPasswordIsHiddenFromDumps(): void
    {
        $c = self::config();
        self::assertStringNotContainsString('test-only-not-a-secret', print_r($c, true));
        self::assertStringNotContainsString('test-only-not-a-secret', var_export($c->__debugInfo(), true));
        self::assertSame('test-only-not-a-secret', $c->smtpPassword());
    }

    public function testPasswordFileMustBePrivate(): void
    {
        $file = self::tempDir() . '/pw';
        file_put_contents($file, "from-file\n");
        chmod($file, 0o644);
        try {
            self::config(['SMTP_PASSWORD' => '', 'SMTP_PASSWORD_FILE' => $file]);
            self::fail('world-readable password file accepted');
        } catch (ConfigException $e) {
            self::assertStringContainsString('chmod 600', $e->getMessage());
        }
        chmod($file, 0o600);
        $config = self::config(['SMTP_PASSWORD' => '', 'SMTP_PASSWORD_FILE' => $file]);
        self::assertSame('from-file', $config->smtpPassword());
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'missing host' => [['SMTP_HOST' => ''], 'SMTP_HOST'];
        yield 'host injection' => [['SMTP_HOST' => "smtp.example.test\r\nX: y"], 'SMTP_HOST'];
        yield 'bad encryption' => [['SMTP_ENCRYPTION' => 'ssl3'], 'SMTP_ENCRYPTION'];
        yield 'bad port' => [['SMTP_PORT' => '70000'], 'SMTP_PORT'];
        yield 'bad from' => [['MAIL_FROM' => 'not-an-address'], 'MAIL_FROM'];
        yield 'header injection in to' => [['MAIL_TO' => "a@example.test\r\nBcc: x@example.test"], 'MAIL_TO'];
        yield 'from name with newline' => [['MAIL_FROM_NAME' => "Web\nBcc: x@example.test"], 'MAIL_FROM_NAME'];
        yield 'user without password' => [['SMTP_PASSWORD' => ''], 'SMTP_PASSWORD'];
        yield 'both password sources' => [['SMTP_PASSWORD_FILE' => '/etc/hostname'], 'not both'];
        yield 'bad rate limit' => [['RATE_LIMIT_MAX' => '0'], 'RATE_LIMIT_MAX'];
        yield 'unreadable ca file' => [['SMTP_CA_FILE' => '/nonexistent/ca.pem'], 'SMTP_CA_FILE'];
    }

    /**
     * @param array<string, string> $overrides
     */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfiguration(array $overrides, string $expected): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($expected, '/') . '/');
        self::config($overrides);
    }
}
