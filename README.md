# emailSenderPhp

[![CI](https://github.com/int3erlud3/emailSenderPhp/actions/workflows/ci.yml/badge.svg)](https://github.com/int3erlud3/emailSenderPhp/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777bb4)
![License: MIT](https://img.shields.io/badge/license-MIT-green)

A small, security-focused PHP mailer: a **command-line tool** and a **contact form** that
send plain-text e-mail through an authenticated SMTP server with verified TLS, using
[PHPMailer](https://github.com/PHPMailer/PHPMailer). It started as a one-file `mail()`
script. This version is a rebuild that shows how to do the same job safely.

```text
                  _ _ ___              _         ___ _
   ___ _ __  __ _(_) / __| ___ _ _  __| |___ _ _| _ \ |_  _ __
  / -_) '  \/ _` | | \__ \/ -_) ' \/ _` / -_) '_|  _/ ' \| '_ \
  \___|_|_|_\__,_|_|_|___/\___|_||_\__,_\___|_| |_| |_||_| .__/
                                                         |_|

+====================================================================+
|  EMAIL SENDER PHP  ::  Secure SMTP Mailer & Contact Form           |
+--------------------------------------------------------------------+
|  PHPMailer over TLS, CSRF, rate limiting, header-injection guards  |
|  v1.0.0  -  Bastion Ops Toolkit  -  by int3erlud3                  |
+====================================================================+
```

![Contact form](docs/screenshot.png)

## Features

- **SMTP with verified TLS.** STARTTLS (port 587, the default) or implicit TLS (465), TLS 1.2+,
  peer and host name verification always on, optional private CA bundle. Plain SMTP needs
  an explicit `SMTP_ALLOW_INSECURE=1` and never carries credentials. It exists only for
  local test servers such as Mailpit.
- **Configuration from environment variables only.** There are no credentials in code or on the command line.
  Prefer `SMTP_PASSWORD_FILE` (it must have mode `0600`). The password is masked in dumps.
- **Contact form protections:**
  - CSRF token: per session, compared in constant time, single use after a successful send.
  - Rate limiting per client IP: sliding window, file based with `flock`, IPs stored hashed.
  - Honeypot field: bots get a fake success and nothing is sent.
  - Minimum fill time.
  - Server-side input validation with length limits and control-character stripping.
  - Header-injection protection: CR/LF in any header value is rejected.
  - The From address is fixed. The visitor's address only goes into `Reply-To`.
  - Strict CSP without `unsafe-inline`, defensive security headers, and a
    `HttpOnly` / `SameSite=Strict` session cookie.
  - Generic error messages for visitors. Details go to the server log only.
- **Vanilla JS front end.** Client-side validation and `fetch` submission with `textContent`-only
  DOM updates. Without JavaScript the form still works (HTML fallback).
- **CLI** for scripts and cron jobs. It supports `--dry-run`, `--json` output and stable exit codes.
- **Quality gates in CI.** PHPUnit (58 tests), PHPStan at level max, PSR-12 via phpcs,
  `composer audit`, gitleaks, and an end-to-end test against Mailpit with docker compose.

## Quick start (local demo with Mailpit)

```bash
docker compose up -d --build
# form:    http://127.0.0.1:8080/
# mailbox: http://127.0.0.1:8025/   (Mailpit catches every message; nothing leaves the machine)
```

Both services listen on `127.0.0.1` only. The demo uses plain SMTP to Mailpit inside the compose
network. Do **not** copy that part of the configuration to production.

## Installation

Requirements: PHP 8.3+ with `mbstring`, Composer 2.

```bash
git clone https://github.com/int3erlud3/emailSenderPhp.git
cd emailSenderPhp
composer install --no-dev --classmap-authoritative
```

`composer.lock` is committed so every install gets the same, audited dependency versions.
The `vendor/` directory is not committed.

## Configuration

All settings come from the environment. `.env.example` lists every variable with an
empty value. Copy it to `.env` for your process manager or export the variables. Never
commit a filled-in file.

| Variable | Required | Default | Description |
|---|---|---|---|
| `SMTP_HOST` | yes | | SMTP server host name |
| `SMTP_PORT` | | 587 / 465 | Port (default depends on encryption) |
| `SMTP_ENCRYPTION` | | `starttls` | `starttls`, `smtps`, or `none` (only with `SMTP_ALLOW_INSECURE=1`) |
| `SMTP_USERNAME` | | | SMTP user. If set, a password is required |
| `SMTP_PASSWORD_FILE` | | | Path to a file containing the password (mode `0600`) |
| `SMTP_PASSWORD` | | | Password (prefer the file variant) |
| `SMTP_CA_FILE` | | system store | CA bundle for a private CA |
| `SMTP_TIMEOUT` | | 15 | Seconds (1-120) |
| `MAIL_FROM` | yes | | Fixed sender address (must be allowed by your SMTP server / SPF / DKIM) |
| `MAIL_FROM_NAME` | | | Sender display name |
| `MAIL_TO` | form | | Recipient of contact form messages |
| `RATE_LIMIT_MAX` / `RATE_LIMIT_WINDOW` | | 5 / 3600 | Max form submissions per IP per window (seconds) |
| `RATE_LIMIT_DIR` | | `$TMPDIR/email-sender-ratelimit` | Rate limit store (created with mode `0700`) |

## Command-line usage

```bash
export SMTP_HOST=smtp.example.com SMTP_USERNAME=mailer SMTP_PASSWORD_FILE=/etc/email-sender/smtp.pw
export MAIL_FROM=noreply@example.com

vendor/bin/send-mail --to ops@example.com --subject "Backup finished" < report.txt
vendor/bin/send-mail --to ops@example.com --subject "Test" --body-file body.txt --dry-run
vendor/bin/send-mail --to ops@example.com --subject "Nightly" --body-file body.txt --json
```

| Option | Description |
|---|---|
| `--to ADDRESS` | Recipient (required) |
| `--subject TEXT` | Subject (required; no line breaks) |
| `--body-file FILE` | Body from a file (default: standard input; UTF-8, max 1 MiB) |
| `--reply-to ADDRESS` | Reply-To address |
| `--dry-run` | Validate configuration and input without connecting |
| `--json` | Machine-readable result on stdout |
| `--no-banner` | Suppress the banner (or set `NO_BANNER=1`) |

Exit codes: `0` sent or dry-run OK, `1` sending failed, `2` usage or validation error,
`3` configuration error.

The banner is printed to **stderr** only when stderr is a terminal. It is never printed with
`--json`, so pipes, cron jobs and log files stay clean.

## Web form deployment

Point the web server's document root at `public/`, **not** at the project root. That keeps
`vendor/`, `src/` and any configuration outside the web root. Serve it over HTTPS. The session
cookie gets the `Secure` flag and HSTS is sent automatically when the request is HTTPS. Behind a
reverse proxy, make the proxy set `REMOTE_ADDR` (for example `mod_remoteip`). The rate limiter
deliberately trusts only `REMOTE_ADDR`, never `X-Forwarded-For` from the client.

The `Dockerfile` shows a hardened setup: multi-stage build, Apache as `www-data` on port 8080,
production `php.ini`, `expose_php=Off`, uploads disabled, no directory listings. In
`docker-compose.yml` it also runs with all capabilities dropped and `no-new-privileges`.

## Development

```bash
composer install
composer check        # phpcs (PSR-12) + PHPStan (level max) + PHPUnit
composer audit
docker compose up -d --build --wait && tests/integration.sh   # end-to-end with Mailpit
```

Project layout:

```text
bin/send-mail          CLI
public/                web root: index.php (form), send.php (endpoint), assets/
src/                   Config, Validator, Mailer, ContactHandler, Csrf, RateLimiter, Http, Banner
tests/                 PHPUnit tests and tests/integration.sh (docker compose + Mailpit)
```

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability and a summary of the design
decisions.

## License

[MIT](LICENSE)
