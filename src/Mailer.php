<?php

declare(strict_types=1);

namespace EmailSender;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends plain-text mail over SMTP with TLS and certificate verification.
 *
 * The From address is always the configured MAIL_FROM (never user input, so SPF/DKIM/DMARC
 * stay aligned); the visitor's address is only used as Reply-To.
 */
final class Mailer
{
    /** @var \Closure(): PHPMailer */
    private \Closure $factory;

    /**
     * @param (\Closure(): PHPMailer)|null $factory for tests
     */
    public function __construct(private readonly Config $config, ?\Closure $factory = null)
    {
        $this->factory = $factory ?? static fn (): PHPMailer => new PHPMailer(true);
    }

    /**
     * @throws MailException
     */
    public function send(Message $message): void
    {
        foreach ([$message->subject, $message->replyToName] as $headerValue) {
            if (!Validator::isHeaderSafe($headerValue)) {
                throw new MailException('header value contains control characters');
            }
        }
        if (!Validator::isValidEmail($message->to)) {
            throw new MailException('invalid recipient address');
        }
        if ($message->replyTo !== null && !Validator::isValidEmail($message->replyTo)) {
            throw new MailException('invalid reply-to address');
        }

        $mail = ($this->factory)();
        try {
            $this->configure($mail);
            $mail->setFrom($this->config->mailFrom, $this->config->mailFromName, false);
            $mail->addAddress($message->to);
            if ($message->replyTo !== null) {
                $mail->addReplyTo($message->replyTo, $message->replyToName);
            }
            $mail->Subject = $message->subject;
            $mail->Body = $message->body;
            $mail->send();
        } catch (PHPMailerException $e) {
            // PHPMailer messages can contain server responses but never the password.
            throw new MailException('sending failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function configure(PHPMailer $mail): void
    {
        $c = $this->config;
        $mail->isSMTP();
        $mail->Host = $c->smtpHost;
        $mail->Port = $c->smtpPort;
        $mail->Timeout = $c->timeout;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->XMailer = ' '; // do not advertise the library version
        $mail->isHTML(false);
        $mail->SMTPDebug = 0;

        if ($c->encryption === Config::ENCRYPTION_SMTPS) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($c->encryption === Config::ENCRYPTION_STARTTLS) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $c->smtpHost,
            'SNI_enabled' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ];
        if ($c->caFile !== null) {
            $ssl['cafile'] = $c->caFile;
        }
        $mail->SMTPOptions = ['ssl' => $ssl];

        if ($c->smtpUsername !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $c->smtpUsername;
            $mail->Password = $c->smtpPassword();
        } else {
            $mail->SMTPAuth = false;
        }
    }
}
