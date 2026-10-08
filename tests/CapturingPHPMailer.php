<?php

declare(strict_types=1);

namespace EmailSender\Tests;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Builds the complete MIME message like a real send, but never opens a connection.
 */
final class CapturingPHPMailer extends PHPMailer
{
    public bool $sent = false;

    public function __construct(private readonly bool $fail = false)
    {
        parent::__construct(true);
    }

    public function postSend(): bool
    {
        if ($this->fail) {
            throw new \PHPMailer\PHPMailer\Exception('SMTP connect() failed.');
        }
        $this->sent = true;
        return true;
    }
}
