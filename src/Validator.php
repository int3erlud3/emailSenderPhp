<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Input validation for everything that can end up in a mail header or body.
 */
final class Validator
{
    public const MAX_NAME = 100;
    public const MAX_SUBJECT = 150;
    public const MAX_MESSAGE = 5000;
    public const MIN_MESSAGE = 10;

    public static function isValidEmail(string $email): bool
    {
        return strlen($email) <= 254
            && self::isHeaderSafe($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * No CR/LF (header injection), no other control characters, valid UTF-8.
     */
    public static function isHeaderSafe(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8') && preg_match('/[\x00-\x1F\x7F]/u', $value) !== 1;
    }

    /**
     * Validates contact form input.
     *
     * @param array<mixed> $input
     * @return array{data: array{name: string, email: string, subject: string, message: string},
     *               errors: array<string, string>}
     */
    public static function contactForm(array $input): array
    {
        $field = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $name = $field('name');
        $email = $field('email');
        $subject = $field('subject');
        // Normalise line endings; strip control characters except newline and tab.
        $message = str_replace(["\r\n", "\r"], "\n", $field('message'));
        $message = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);

        $errors = [];
        if ($name === '' || mb_strlen($name) > self::MAX_NAME || !self::isHeaderSafe($name)) {
            $errors['name'] = 'Please enter your name (max. ' . self::MAX_NAME . ' characters).';
        }
        if (!self::isValidEmail($email)) {
            $errors['email'] = 'Please enter a valid e-mail address.';
        }
        if ($subject === '' || mb_strlen($subject) > self::MAX_SUBJECT || !self::isHeaderSafe($subject)) {
            $errors['subject'] = 'Please enter a subject (max. ' . self::MAX_SUBJECT . ' characters).';
        }
        if (!mb_check_encoding($message, 'UTF-8')) {
            $errors['message'] = 'The message contains invalid characters.';
        } elseif (mb_strlen($message) < self::MIN_MESSAGE || mb_strlen($message) > self::MAX_MESSAGE) {
            $errors['message'] = 'The message must be between ' . self::MIN_MESSAGE . ' and '
                . self::MAX_MESSAGE . ' characters.';
        }

        return [
            'data' => ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message],
            'errors' => $errors,
        ];
    }
}
