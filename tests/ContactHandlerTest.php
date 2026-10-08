<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\ContactHandler;
use EmailSender\Csrf;
use EmailSender\Mailer;
use EmailSender\RateLimiter;
use PHPMailer\PHPMailer\PHPMailer;

final class ContactHandlerTest extends TestCase
{
    private CapturingPHPMailer $capture;
    private int $now = 10_000;
    /** @var array<string, mixed> */
    private array $session = [];

    private function handler(int $max = 5, bool $fail = false): ContactHandler
    {
        $this->capture = new CapturingPHPMailer($fail);
        $capture = $this->capture;
        $clock = fn (): int => $this->now;
        return new ContactHandler(
            new Mailer(self::config(), static fn (): PHPMailer => $capture),
            new RateLimiter(self::tempDir() . '/rl', $max, 3600, $clock),
            'inbox@example.test',
            $clock,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function post(array $overrides = []): array
    {
        $token = Csrf::token($this->session, $this->now - 10);
        return array_merge([
            'csrf_token' => $token, 'name' => 'Jane Doe', 'email' => 'jane@example.com',
            'subject' => 'Question', 'message' => 'Hello, I have a question about your service.', 'website' => '',
        ], $overrides);
    }

    public function testValidSubmissionIsSentAndTokenRotated(): void
    {
        $handler = $this->handler();
        $post = $this->post();
        $r = $handler->handle($post, '192.0.2.10', $this->session);
        self::assertSame(200, $r->status);
        self::assertTrue($r->ok);
        self::assertTrue($this->capture->sent);
        $mime = $this->capture->getSentMIMEMessage();
        self::assertStringContainsString('Subject: [Contact] Question', $mime);
        self::assertStringContainsString('Reply-To: Jane Doe <jane@example.com>', $mime);
        self::assertStringContainsString('To: inbox@example.test', $mime);
        self::assertNotSame($post['csrf_token'], Csrf::token($this->session));
    }

    public function testHoneypotPretendsSuccessButSendsNothing(): void
    {
        $handler = $this->handler();
        $r = $handler->handle($this->post(['website' => 'http://spam.example']), '192.0.2.10', $this->session);
        self::assertSame(200, $r->status);
        self::assertFalse($this->capture->sent);
    }

    public function testMissingOrWrongCsrfTokenIsForbidden(): void
    {
        $handler = $this->handler();
        self::assertSame(403, $handler->handle($this->post(['csrf_token' => 'x']), 'ip', $this->session)->status);
        self::assertSame(403, $handler->handle($this->post(['csrf_token' => null]), 'ip', $this->session)->status);
        $noSession = [];
        self::assertSame(403, $handler->handle($this->post(), 'ip', $noSession)->status);
        self::assertFalse($this->capture->sent);
    }

    public function testTooFastSubmissionIsRejected(): void
    {
        $handler = $this->handler();
        $this->session = [];
        $token = Csrf::token($this->session, $this->now - 1);
        $r = $handler->handle($this->post(['csrf_token' => $token]), 'ip', $this->session);
        self::assertSame(429, $r->status);
    }

    public function testRateLimitPerClient(): void
    {
        $handler = $this->handler(max: 2);
        $bad = ['message' => 'short'];
        $codes = [];
        $attempts = [[$bad, '198.51.100.1'], [$bad, '198.51.100.1'], [[], '198.51.100.1'], [[], '198.51.100.2']];
        foreach ($attempts as [$o, $ip]) {
            $codes[] = $handler->handle($this->post($o), $ip, $this->session)->status;
        }
        self::assertSame([422, 422, 429, 200], $codes);
    }

    public function testValidationErrorsAreReported(): void
    {
        $handler = $this->handler();
        $r = $handler->handle($this->post(['email' => "x@example.com\r\nBcc: spam@example.net"]), 'ip', $this->session);
        self::assertSame(422, $r->status);
        self::assertArrayHasKey('email', $r->errors);
        self::assertFalse($this->capture->sent);
    }

    public function testTransportErrorIsGenericForTheVisitor(): void
    {
        $handler = $this->handler(fail: true);
        $log = ini_set('error_log', '/dev/null');
        $r = $handler->handle($this->post(), 'ip', $this->session);
        ini_set('error_log', (string) $log);
        self::assertSame(500, $r->status);
        self::assertStringNotContainsString('connect', $r->message);
    }
}
