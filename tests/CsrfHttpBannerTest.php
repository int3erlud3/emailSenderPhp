<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\Banner;
use EmailSender\Csrf;
use EmailSender\Http;

final class CsrfHttpBannerTest extends TestCase
{
    public function testCsrfTokenLifecycle(): void
    {
        $session = [];
        $token = Csrf::token($session, 123);
        self::assertSame(64, strlen($token));
        self::assertSame($token, Csrf::token($session), 'stable within a session');
        self::assertSame(123, Csrf::issuedAt($session));
        self::assertTrue(Csrf::validate($session, $token));
        self::assertFalse(Csrf::validate($session, strtoupper($token)));
        self::assertFalse(Csrf::validate($session, ['array']));
        Csrf::rotate($session);
        self::assertFalse(Csrf::validate($session, $token));
    }

    public function testSecurityHeaders(): void
    {
        $h = Http::securityHeaders();
        self::assertStringContainsString("default-src 'none'", $h['Content-Security-Policy']);
        self::assertStringContainsString("form-action 'self'", $h['Content-Security-Policy']);
        self::assertStringNotContainsString('unsafe-inline', $h['Content-Security-Policy']);
        self::assertSame('nosniff', $h['X-Content-Type-Options']);
        self::assertSame('DENY', $h['X-Frame-Options']);
        self::assertSame('&lt;script&gt;&quot;x&quot;', Http::escape('<script>"x"'));
    }

    public function testBannerRendering(): void
    {
        $text = Banner::render();
        foreach (explode("\n", $text) as $line) {
            self::assertLessThanOrEqual(70, strlen($line));
        }
        self::assertMatchesRegularExpression('/^[\x20-\x7E\n]+$/', $text);
        self::assertStringContainsString(Banner::SUITE, $text);
        self::assertStringContainsString('v' . Banner::VERSION, $text);
        self::assertStringContainsString('by int3erlud3', $text);
    }

    public function testReadmeShowsCurrentBanner(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');
        self::assertStringContainsString(Banner::render(), $readme);
    }

    public function testBannerRules(): void
    {
        $file = fopen('php://memory', 'w+');
        self::assertIsResource($file);
        self::assertFalse(Banner::enabled(false, $file, ''), 'not a TTY');
        self::assertFalse(Banner::maybePrint(false, $file));
        self::assertFalse(Banner::enabled(true, $file, ''));
        self::assertFalse(Banner::enabled(false, $file, '1'));
        rewind($file);
        self::assertSame('', stream_get_contents($file));
    }
}
