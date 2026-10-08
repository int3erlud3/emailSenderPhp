<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Processes one contact form submission:
 * honeypot -> CSRF -> minimum fill time -> rate limit -> validation -> send.
 */
final class ContactHandler
{
    public const HONEYPOT_FIELD = 'website';
    public const MIN_FILL_SECONDS = 3;

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        private readonly Mailer $mailer,
        private readonly RateLimiter $limiter,
        private readonly string $recipient,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<mixed> $post
     * @param array<string, mixed> $session
     */
    public function handle(array $post, string $clientIp, array &$session): ContactResult
    {
        $thanks = 'Thank you, your message has been sent.';

        // Bots fill every field: pretend success, send nothing.
        $honeypot = $post[self::HONEYPOT_FIELD] ?? '';
        if (!is_string($honeypot) || $honeypot !== '') {
            Csrf::rotate($session);
            return new ContactResult(200, true, $thanks);
        }

        if (!Csrf::validate($session, $post['csrf_token'] ?? null)) {
            return new ContactResult(403, false, 'Your session has expired. Please reload the page and try again.');
        }

        $issued = Csrf::issuedAt($session);
        if ($issued !== null && ($this->clock)() - $issued < self::MIN_FILL_SECONDS) {
            return new ContactResult(429, false, 'Please take a moment before submitting the form.');
        }

        if (!$this->limiter->hit('contact|' . $clientIp)) {
            return new ContactResult(429, false, 'Too many messages. Please try again later.');
        }

        $result = Validator::contactForm($post);
        if ($result['errors'] !== []) {
            return new ContactResult(422, false, 'Please correct the highlighted fields.', $result['errors']);
        }
        $d = $result['data'];

        $body = "Message from the contact form\n\n"
            . 'Name:    ' . $d['name'] . "\n"
            . 'E-mail:  ' . $d['email'] . "\n"
            . 'Subject: ' . $d['subject'] . "\n\n"
            . $d['message'] . "\n";

        try {
            $this->mailer->send(new Message(
                $this->recipient,
                '[Contact] ' . $d['subject'],
                $body,
                $d['email'],
                $d['name'],
            ));
        } catch (MailException $e) {
            // Log the technical reason server-side only; never echo it to the visitor.
            error_log('email-sender: ' . $e->getMessage());
            return new ContactResult(500, false, 'The message could not be sent. Please try again later.');
        }

        Csrf::rotate($session);
        return new ContactResult(200, true, $thanks);
    }
}
