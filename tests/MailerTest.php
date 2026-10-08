<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use EmailSender\MailException;
use EmailSender\Mailer;
use EmailSender\Message;
use PHPMailer\PHPMailer\PHPMailer;

final class MailerTest extends TestCase
{
    public function testSmtpSettingsUseVerifiedTls(): void
    {
        $mail = new PHPMailer(true);
        (new Mailer(self::config(['SMTP_CA_FILE' => __FILE__])))->configure($mail);
        self::assertSame('smtp', $mail->Mailer);
        self::assertSame(PHPMailer::ENCRYPTION_STARTTLS, $mail->SMTPSecure);
        self::assertTrue($mail->SMTPAuth);
        self::assertSame('mailer', $mail->Username);
        $ssl = $mail->SMTPOptions['ssl'] ?? null;
        self::assertIsArray($ssl);
        self::assertTrue($ssl['verify_peer']);
        self::assertTrue($ssl['verify_peer_name']);
        self::assertFalse($ssl['allow_self_signed']);
        self::assertSame(__FILE__, $ssl['cafile']);
        self::assertSame(0, $mail->SMTPDebug);
    }

    public function testSmtpsAndLocalPlaintext(): void
    {
        $mail = new PHPMailer(true);
        (new Mailer(self::config(['SMTP_ENCRYPTION' => 'smtps', 'SMTP_PORT' => '465'])))->configure($mail);
        self::assertSame(PHPMailer::ENCRYPTION_SMTPS, $mail->SMTPSecure);

        $local = self::config([
            'SMTP_HOST' => 'mailpit', 'SMTP_PORT' => '1025', 'SMTP_ENCRYPTION' => 'none',
            'SMTP_ALLOW_INSECURE' => '1', 'SMTP_USERNAME' => '', 'SMTP_PASSWORD' => '',
        ]);
        $mail = new PHPMailer(true);
        (new Mailer($local))->configure($mail);
        self::assertSame('', $mail->SMTPSecure);
        self::assertFalse($mail->SMTPAutoTLS);
        self::assertFalse($mail->SMTPAuth);
    }

    public function testBuildsPlainTextMessageWithFixedFromAndReplyTo(): void
    {
        $capture = new CapturingPHPMailer();
        $mailer = new Mailer(self::config(), static fn (): PHPMailer => $capture);
        $mailer->send(new Message('inbox@example.test', 'Grüße', "Hello\nWorld", 'jane@example.com', 'Jane Doe'));

        self::assertTrue($capture->sent);
        $mime = $capture->getSentMIMEMessage();
        self::assertStringContainsString('From: Website <noreply@example.test>', $mime);
        self::assertStringContainsString('Reply-To: Jane Doe <jane@example.com>', $mime);
        self::assertStringContainsString('To: inbox@example.test', $mime);
        self::assertStringContainsString('Content-Type: text/plain; charset=utf-8', $mime);
        self::assertStringNotContainsString('X-Mailer: PHPMailer', $mime);
        self::assertStringNotContainsString('test-only-not-a-secret', $mime);
    }

    public function testRejectsHeaderInjection(): void
    {
        $mailer = new Mailer(self::config(), static fn (): PHPMailer => new CapturingPHPMailer());
        $cases = [
            new Message('inbox@example.test', "Hi\r\nBcc: spam@example.net", 'body'),
            new Message("inbox@example.test\r\nBcc: spam@example.net", 'Hi', 'body'),
            new Message('inbox@example.test', 'Hi', 'body', "jane@example.com\nBcc: spam@example.net"),
            new Message('inbox@example.test', 'Hi', 'body', 'jane@example.com', "Jane\r\nBcc: spam@example.net"),
        ];
        foreach ($cases as $message) {
            try {
                $mailer->send($message);
                self::fail('header injection accepted');
            } catch (MailException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTransportErrorsBecomeMailException(): void
    {
        $mailer = new Mailer(self::config(), static fn (): PHPMailer => new CapturingPHPMailer(fail: true));
        $this->expectException(MailException::class);
        $this->expectExceptionMessageMatches('/connect\(\) failed/');
        $mailer->send(new Message('inbox@example.test', 'Hi', 'body'));
    }
}
