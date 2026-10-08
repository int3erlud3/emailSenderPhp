<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidatorTest extends TestCase
{
    /**
     * @return iterable<array{string, bool}>
     */
    public static function emails(): iterable
    {
        yield ['user@example.com', true];
        yield ['first.last+tag@sub.example.org', true];
        yield ['no-at-sign.example.com', false];
        yield ["victim@example.com\r\nBcc: spam@example.net", false];
        yield ["victim@example.com\nCc: spam@example.net", false];
        yield ['a@b', false];
        yield [str_repeat('a', 250) . '@example.com', false];
        yield ["user\0@example.com", false];
    }

    #[DataProvider('emails')]
    public function testEmailValidation(string $email, bool $valid): void
    {
        self::assertSame($valid, Validator::isValidEmail($email));
    }

    public function testHeaderSafety(): void
    {
        self::assertTrue(Validator::isHeaderSafe('Grüße aus Berlin'));
        self::assertFalse(Validator::isHeaderSafe("Hi\r\nBcc: x@example.com"));
        self::assertFalse(Validator::isHeaderSafe("tab\there"));
        self::assertFalse(Validator::isHeaderSafe("\xC3\x28"));
    }

    public function testValidContactForm(): void
    {
        $r = Validator::contactForm([
            'name' => '  Jane Doe ', 'email' => 'jane@example.com', 'subject' => 'Hello',
            'message' => "Line one\r\nLine two\x07 with bell",
        ]);
        self::assertSame([], $r['errors']);
        self::assertSame('Jane Doe', $r['data']['name']);
        self::assertSame("Line one\nLine two with bell", $r['data']['message']);
    }

    public function testInvalidContactForm(): void
    {
        $r = Validator::contactForm([
            'name' => "Eve\r\nBcc: x@example.com", 'email' => 'bad', 'subject' => "Hi\nBcc: x@example.com",
            'message' => 'short',
        ]);
        self::assertSame(['name', 'email', 'subject', 'message'], array_keys($r['errors']));
    }

    public function testNonStringInputIsTreatedAsEmpty(): void
    {
        $r = Validator::contactForm(['name' => ['array'], 'email' => 42, 'subject' => null, 'message' => true]);
        self::assertCount(4, $r['errors']);
    }

    public function testLengthLimits(): void
    {
        $r = Validator::contactForm([
            'name' => str_repeat('n', 101), 'email' => 'a@example.com',
            'subject' => str_repeat('s', 151), 'message' => str_repeat('m', 5001),
        ]);
        self::assertSame(['name', 'subject', 'message'], array_keys($r['errors']));
    }
}
