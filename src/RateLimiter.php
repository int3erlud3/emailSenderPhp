<?php

declare(strict_types=1);

namespace EmailSender;

/**
 * Sliding-window rate limiter backed by one small JSON file per client key.
 * Keys are hashed, files are created with mode 0600 and updated under flock().
 */
final class RateLimiter
{
    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param (\Closure(): int)|null $clock for tests
     */
    public function __construct(
        private readonly string $dir,
        private readonly int $max,
        private readonly int $window,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Records an attempt and returns whether it is allowed.
     */
    public function hit(string $key): bool
    {
        $this->ensureDir();
        $file = $this->dir . '/' . hash('sha256', $key) . '.json';
        $old = umask(0o077);
        $fh = fopen($file, 'c+');
        umask($old);
        if ($fh === false) {
            throw new \RuntimeException('cannot open rate limit store');
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                throw new \RuntimeException('cannot lock rate limit store');
            }
            $now = ($this->clock)();
            $raw = stream_get_contents($fh);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $hits = array_values(array_filter(
                is_array($decoded) ? $decoded : [],
                fn ($t): bool => is_int($t) && $t > $now - $this->window,
            ));
            $allowed = count($hits) < $this->max;
            if ($allowed) {
                $hits[] = $now;
            }
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode($hits));
            fflush($fh);
            return $allowed;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o700, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('cannot create rate limit directory');
        }
        if (is_link($this->dir)) {
            throw new \RuntimeException('rate limit directory must not be a symlink');
        }
    }
}
