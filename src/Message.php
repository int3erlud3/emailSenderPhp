<?php

declare(strict_types=1);

namespace EmailSender;

final class Message
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $replyTo = null,
        public readonly string $replyToName = '',
    ) {
    }
}
