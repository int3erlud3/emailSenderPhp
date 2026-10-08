<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\RateLimiter;

final class RateLimiterTest extends TestCase
{
    public function testSlidingWindow(): void
    {
        $now = 1_000;
        $limiter = new RateLimiter(self::tempDir() . '/rl', 3, 60, static function () use (&$now): int {
            return $now;
        });
        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertTrue($limiter->hit('1.2.3.4'));
        self::assertFalse($limiter->hit('1.2.3.4'));
        self::assertTrue($limiter->hit('5.6.7.8'), 'other clients are independent');
        $now += 61;
        self::assertTrue($limiter->hit('1.2.3.4'), 'window expired');
    }

    public function testStoreIsPrivateAndKeysAreHashed(): void
    {
        $dir = self::tempDir() . '/rl';
        (new RateLimiter($dir, 5, 60))->hit('192.0.2.1');
        self::assertSame(0o700, fileperms($dir) & 0o777);
        $files = glob($dir . '/*.json') ?: [];
        self::assertCount(1, $files);
        self::assertStringNotContainsString('192.0.2.1', $files[0]);
        self::assertSame(0o600, fileperms($files[0]) & 0o777);
    }

    public function testCorruptStoreIsReset(): void
    {
        $dir = self::tempDir() . '/rl';
        $limiter = new RateLimiter($dir, 1, 60);
        $limiter->hit('k');
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            file_put_contents($f, '{garbage');
        }
        self::assertTrue($limiter->hit('k'));
    }
}
