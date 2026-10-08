<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\Banner;

/**
 * Runs bin/send-mail as a subprocess (no SMTP connection: --dry-run / validation errors).
 */
final class CliTest extends TestCase
{
    private const ENV = [
        'SMTP_HOST' => 'smtp.example.test', 'SMTP_USERNAME' => 'mailer', 'SMTP_PASSWORD' => 'test-only-not-a-secret',
        'MAIL_FROM' => 'noreply@example.test', 'MAIL_TO' => 'inbox@example.test', 'NO_BANNER' => '',
    ];

    /**
     * @param list<string> $args
     * @param array<string, string> $env
     * @return array{int, string, string}
     */
    private static function exec(array $args, string $stdin = '', array $env = self::ENV, bool $tty = false): array
    {
        $cmd = array_merge([PHP_BINARY, dirname(__DIR__) . '/bin/send-mail'], $args);
        if ($tty) {
            $quoted = implode(' ', array_map('escapeshellarg', $cmd));
            $cmd = ['script', '-qefc', $quoted, '/dev/null'];
        }
        $env['PATH'] = (string) getenv('PATH');
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        self::assertIsResource($proc);
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $out, $err];
    }

    private static function hasScript(): bool
    {
        return trim((string) shell_exec('command -v script')) !== '';
    }

    public function testDryRunValidatesEverything(): void
    {
        [$code, $out] = self::exec(['--to', 'a@example.com', '--subject', 'Hi', '--dry-run'], "Body\n");
        self::assertSame(0, $code);
        self::assertStringContainsString(
            'dry-run OK: would send to a@example.com via smtp.example.test:587 (starttls)',
            $out,
        );
    }

    public function testJsonOutput(): void
    {
        [$code, $out] = self::exec(['--to', 'a@example.com', '--subject', 'Hi', '--dry-run', '--json'], 'Body');
        self::assertSame(0, $code);
        $data = json_decode($out, true);
        self::assertIsArray($data);
        self::assertSame('dry_run', $data['status']);
    }

    public function testHeaderInjectionInArgumentsIsRejected(): void
    {
        [$code] = self::exec(['--to', "a@example.com\r\nBcc: x@example.net", '--subject', 'Hi'], 'Body');
        self::assertSame(2, $code);
        [$code] = self::exec(['--to', 'a@example.com', '--subject', "Hi\nBcc: x@example.net"], 'Body');
        self::assertSame(2, $code);
    }

    public function testEmptyBodyAndUnknownArgs(): void
    {
        self::assertSame(2, self::exec(['--to', 'a@example.com', '--subject', 'Hi'], '')[0]);
        self::assertSame(2, self::exec(['--bogus'])[0]);
        self::assertSame(2, self::exec(['--to'])[0]);
    }

    public function testConfigErrorExit3WithoutLeakingSecrets(): void
    {
        $env = self::ENV;
        unset($env['SMTP_HOST']);
        [$code, $out, $err] = self::exec(['--to', 'a@example.com', '--subject', 'Hi'], 'Body', $env);
        self::assertSame(3, $code);
        self::assertStringContainsString('SMTP_HOST', $err);
        self::assertStringNotContainsString('test-only-not-a-secret', $out . $err);
    }

    public function testVersionAndHelp(): void
    {
        self::assertSame("send-mail 1.0.0\n", self::exec(['--version'])[1]);
        self::assertStringContainsString('Usage: send-mail', self::exec(['--help'])[1]);
    }

    public function testNoBannerWithoutTty(): void
    {
        [, $out, $err] = self::exec(['--version']);
        self::assertStringNotContainsString(Banner::SUITE, $out . $err);
    }

    public function testBannerOnTtyAndSuppression(): void
    {
        if (!self::hasScript()) {
            self::markTestSkipped('script(1) not available');
        }
        self::assertStringContainsString(Banner::SUITE, self::exec(['--version'], tty: true)[1]);
        self::assertStringNotContainsString(Banner::SUITE, self::exec(['--version', '--no-banner'], tty: true)[1]);
        $env = self::ENV;
        $env['NO_BANNER'] = '1';
        self::assertStringNotContainsString(Banner::SUITE, self::exec(['--version'], '', $env, true)[1]);
        $args = ['--to', 'a@example.com', '--subject', 'Hi', '--dry-run', '--json', '--body-file', __FILE__];
        $json = self::exec($args, tty: true);
        self::assertStringNotContainsString(Banner::SUITE, $json[1]);
    }
}
