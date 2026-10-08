# Security Policy

## Reporting a vulnerability

Please do **not** open a public issue for security problems. Use GitHub's
[private vulnerability reporting](../../security/advisories/new) for this repository
instead. You can expect an initial response within 7 days.

## Security review notes

- **Transport.** SMTP over STARTTLS or implicit TLS with peer and host name verification and a
  TLS 1.2 minimum. Plain SMTP requires an explicit opt-in and refuses to send credentials.
- **Secrets.** Read from environment variables or a `0600` password file only. Never accepted
  as command-line arguments, never logged, and masked in `var_dump`/`print_r`. The repository
  contains only an empty `.env.example`.
- **Header injection.** Every header value (addresses, names, subject) is rejected if it
  contains CR, LF or other control characters. The sender address is fixed by configuration.
- **Form abuse.** CSRF token (single use after a successful send), honeypot, minimum fill time,
  per-IP rate limit with hashed keys, request size limit, and strict server-side validation.
- **Browser hardening.** CSP `default-src 'none'` without inline script or style,
  `X-Frame-Options: DENY`, `nosniff`, `no-referrer`, and a `HttpOnly` + `SameSite=Strict` session
  cookie (plus `Secure` and HSTS on HTTPS). All output is HTML-escaped. The JS uses
  `textContent` only.
- **Errors.** Visitors get generic messages. SMTP details go to the server error log.
- **Supply chain.** Dependencies are locked in `composer.lock` and checked with
  `composer audit` (vulnerable and abandoned packages fail the build). GitHub Actions are pinned
  to commit SHAs and run with `contents: read`. gitleaks scans the full history.
