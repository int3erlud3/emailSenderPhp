<?php

declare(strict_types=1);

namespace EmailSender;

final class ContactResult
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        public readonly int $status,
        public readonly bool $ok,
        public readonly string $message,
        public readonly array $errors = [],
    ) {
    }

    /**
     * @return array{ok: bool, message: string, errors: array<string, string>}
     */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'message' => $this->message, 'errors' => $this->errors];
    }
}
