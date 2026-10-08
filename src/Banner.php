<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Bastion Ops Toolkit banner (cosmetic): written to STDERR only when it is an interactive
 * terminal, never with --json. Disable with --no-banner or NO_BANNER=1.
 */
final class Banner
{
    public const SUITE = 'Bastion Ops Toolkit';
    public const VERSION = '1.0.0';

    private const ART = <<<'ART'
                  _ _ ___              _         ___ _
   ___ _ __  __ _(_) / __| ___ _ _  __| |___ _ _| _ \ |_  _ __
  / -_) '  \/ _` | | \__ \/ -_) ' \/ _` / -_) '_|  _/ ' \| '_ \
  \___|_|_|_\__,_|_|_|___/\___|_||_\__,_\___|_| |_| |_||_| .__/
                                                         |_|

+====================================================================+
|  EMAIL SENDER PHP  ::  Secure SMTP Mailer & Contact Form           |
+--------------------------------------------------------------------+
|  PHPMailer over TLS, CSRF, rate limiting, header-injection guards  |
ART;

    public static function render(): string
    {
        $info = 'v' . self::VERSION . '  -  ' . self::SUITE . '  -  by int3erlud3';
        return self::ART . "\n" . '|  ' . str_pad($info, 64) . '  |' . "\n" . '+' . str_repeat('=', 68) . '+';
    }

    /**
     * @param resource|null $stream
     */
    public static function enabled(bool $noBanner = false, $stream = null, ?string $envValue = null): bool
    {
        if ($noBanner) {
            return false;
        }
        $env = $envValue ?? (getenv('NO_BANNER') === false ? '' : (string) getenv('NO_BANNER'));
        if ($env !== '' && $env !== '0') {
            return false;
        }
        $stream ??= defined('STDERR') ? STDERR : null;
        return is_resource($stream) && stream_isatty($stream);
    }

    /**
     * @param resource|null $stream
     */
    public static function maybePrint(bool $noBanner = false, $stream = null): bool
    {
        $stream ??= defined('STDERR') ? STDERR : null;
        if ($stream === null || !self::enabled($noBanner, $stream)) {
            return false;
        }
        return @fwrite($stream, self::render() . "\n\n") !== false;
    }
}
