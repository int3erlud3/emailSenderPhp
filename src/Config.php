<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Runtime configuration, read from environment variables only.
 *
 * Secrets never live in code or in the repository: use real environment variables,
 * a docker-compose env_file, or SMTP_PASSWORD_FILE pointing to a 0600 file.
 */
final class Config
{
    public const ENCRYPTION_STARTTLS = 'starttls';
    public const ENCRYPTION_SMTPS = 'smtps';
    public const ENCRYPTION_NONE = 'none';

    /**
     * @param non-empty-string $smtpHost
     */
    private function __construct(
        public readonly string $smtpHost,
        public readonly int $smtpPort,
        public readonly string $encryption,
        public readonly string $smtpUsername,
        #[\SensitiveParameter] private readonly string $smtpPassword,
        public readonly string $mailFrom,
        public readonly string $mailFromName,
        public readonly string $mailTo,
        public readonly int $timeout,
        public readonly ?string $caFile,
        public readonly int $rateLimitMax,
        public readonly int $rateLimitWindow,
        public readonly string $rateLimitDir,
    ) {
    }

    /**
     * @param array<string, string> $env
     */
    public static function fromEnv(array $env): self
    {
        // An empty variable means "not set", so an empty .env.example entry falls back to the default.
        $get = static function (string $key, string $default = '') use ($env): string {
            $value = trim((string) ($env[$key] ?? ''));
            return $value === '' ? $default : $value;
        };

        $host = $get('SMTP_HOST');
        if ($host === '' || preg_match('/^[A-Za-z0-9.-]{1,253}$/', $host) !== 1) {
            throw new ConfigException('SMTP_HOST must be set to a valid host name');
        }

        $encryption = strtolower($get('SMTP_ENCRYPTION', self::ENCRYPTION_STARTTLS));
        if (!in_array($encryption, [self::ENCRYPTION_STARTTLS, self::ENCRYPTION_SMTPS, self::ENCRYPTION_NONE], true)) {
            throw new ConfigException('SMTP_ENCRYPTION must be starttls, smtps or none');
        }
        if ($encryption === self::ENCRYPTION_NONE && $get('SMTP_ALLOW_INSECURE') !== '1') {
            throw new ConfigException(
                'SMTP_ENCRYPTION=none sends credentials and mail in clear text; '
                . 'set SMTP_ALLOW_INSECURE=1 only for a local test server such as Mailpit'
            );
        }

        $defaultPort = $encryption === self::ENCRYPTION_SMTPS ? '465' : '587';
        $port = self::int($get('SMTP_PORT', $defaultPort), 'SMTP_PORT', 1, 65535);

        $username = $get('SMTP_USERNAME');
        $password = (string) ($env['SMTP_PASSWORD'] ?? '');
        $passwordFile = $get('SMTP_PASSWORD_FILE');
        if ($passwordFile !== '') {
            if ($password !== '') {
                throw new ConfigException('set either SMTP_PASSWORD or SMTP_PASSWORD_FILE, not both');
            }
            $password = self::readSecretFile($passwordFile);
        }
        if ($username !== '' && $password === '') {
            throw new ConfigException('SMTP_USERNAME is set but no SMTP_PASSWORD / SMTP_PASSWORD_FILE');
        }
        if ($username !== '' && $encryption === self::ENCRYPTION_NONE) {
            throw new ConfigException('refusing to send SMTP credentials without TLS');
        }

        $from = $get('MAIL_FROM');
        $to = $get('MAIL_TO');
        foreach (['MAIL_FROM' => $from, 'MAIL_TO' => $to] as $key => $address) {
            if (!Validator::isValidEmail($address)) {
                throw new ConfigException("$key must be a valid e-mail address");
            }
        }
        $fromName = $get('MAIL_FROM_NAME', 'Contact form');
        if (!Validator::isHeaderSafe($fromName) || mb_strlen($fromName) > 100) {
            throw new ConfigException('MAIL_FROM_NAME contains invalid characters');
        }

        $caFile = $get('SMTP_CA_FILE');
        if ($caFile !== '' && !is_readable($caFile)) {
            throw new ConfigException('SMTP_CA_FILE is not readable');
        }

        $rateDir = $get('RATE_LIMIT_DIR', rtrim(sys_get_temp_dir(), '/') . '/email-sender-ratelimit');

        return new self(
            $host,
            $port,
            $encryption,
            $username,
            $password,
            $from,
            $fromName,
            $to,
            self::int($get('SMTP_TIMEOUT', '15'), 'SMTP_TIMEOUT', 1, 120),
            $caFile !== '' ? $caFile : null,
            self::int($get('RATE_LIMIT_MAX', '5'), 'RATE_LIMIT_MAX', 1, 1000),
            self::int($get('RATE_LIMIT_WINDOW', '3600'), 'RATE_LIMIT_WINDOW', 10, 86400),
            $rateDir,
        );
    }

    public function smtpPassword(): string
    {
        return $this->smtpPassword;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['smtpHost' => $this->smtpHost, 'smtpPort' => $this->smtpPort, 'encryption' => $this->encryption,
            'smtpUsername' => $this->smtpUsername, 'smtpPassword' => '***'];
    }

    private static function int(string $value, string $key, int $min, int $max): int
    {
        if (preg_match('/^\d{1,6}$/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
            throw new ConfigException("$key must be an integer between $min and $max");
        }
        return (int) $value;
    }

    private static function readSecretFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ConfigException('SMTP_PASSWORD_FILE is not a readable file');
        }
        $perms = fileperms($path);
        if ($perms !== false && ($perms & 0o077) !== 0) {
            throw new ConfigException('SMTP_PASSWORD_FILE must not be accessible by group/others (chmod 600)');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new ConfigException('cannot read SMTP_PASSWORD_FILE');
        }
        return rtrim($content, "\r\n");
    }
}
