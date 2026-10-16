# Integration Register

Every external dependency, its required credentials, and its **true** status.

## Status levels

| Level | Meaning |
|---|---|
| **Not implemented** | No adapter code exists |
| **Implemented** | Adapter code + automated tests against fakes/fixtures exist |
| **Configured** | Real (sandbox or live) credentials are present in an environment |
| **Sandbox-tested** | A real call/transaction succeeded against the provider's test environment, with evidence recorded below |
| **Production-verified** | A real live call/transaction succeeded in production, with evidence recorded below |

A configuration form existing in the admin panel does **not** change status.

## Register (updated 2026-10-14, Milestone 1)

| Integration | Purpose | Milestone | Status | Credentials / account needed | Environment variables (planned) |
|---|---|---|---|---|---|
| Paystack | Payments, refunds, webhooks | M3 | Not implemented | Paystack business account (KYC), test + live secret/public keys, webhook URL registered | `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`, `PAYSTACK_BASE_URL` |
| Flutterwave | Payments, refunds, webhooks | M3 | Not implemented | Flutterwave business account, test + live keys, secret hash for webhooks | `FLUTTERWAVE_PUBLIC_KEY`, `FLUTTERWAVE_SECRET_KEY`, `FLUTTERWAVE_ENCRYPTION_KEY`, `FLUTTERWAVE_WEBHOOK_HASH` |
| Transactional email | Verification, reset, receipts, reminders | M1 | **Implemented** (queued notifications + delivery log; `log` mailer only — no provider chosen or configured) | Provider account (Postmark / Resend / SES / Brevo — Q6), verified sending domain, SPF/DKIM/DMARC | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, provider token |
| Object storage | Public images + private paid files | M2/M3 | Not implemented | S3-compatible bucket(s) (R2/B2/S3), access key with least privilege | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_PRIVATE_BUCKET`, `AWS_ENDPOINT`, `AWS_URL` |
| Video streaming | Protected lesson playback | M4 | Not implemented | Bunny Stream library (or Mux/Cloudflare Stream — Q5), API key, token-auth key | `VIDEO_PROVIDER`, `BUNNY_STREAM_LIBRARY_ID`, `BUNNY_STREAM_API_KEY`, `BUNNY_STREAM_TOKEN_KEY`, `BUNNY_STREAM_CDN_HOST` |
| Google reCAPTCHA | Optional CAPTCHA | M1 | **Implemented** (server-side siteverify; tested against HTTP fakes only — no site keys yet) | Google reCAPTCHA site (v3 or v2), site key + secret | `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` |
| Cloudflare Turnstile | Optional CAPTCHA | M1 | **Implemented** (server-side siteverify; tested against HTTP fakes only — no site keys yet) | Cloudflare account, Turnstile widget, site key + secret | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` |
| First-party CAPTCHA | Default CAPTCHA | M1 | **Implemented and verified locally** (no external service; 27 automated tests + end-to-end through the proxy) | None (uses `APP_KEY`-derived HMAC key) | `CAPTCHA_DRIVER=first_party`, `CAPTCHA_DIFFICULTY` |
| Calendly | External scheduling sync | M5 | Not implemented | Calendly paid plan with API/webhooks, OAuth app or personal access token | `CALENDLY_CLIENT_ID`, `CALENDLY_CLIENT_SECRET`, `CALENDLY_WEBHOOK_SIGNING_KEY` |
| Redis | Queues, cache, locks, rate limits | M1 (prod) | Not implemented | Redis on host or managed | `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` |
| Error monitoring | Exceptions/alerts | M8 | Not implemented | Sentry (or similar) project DSN | `SENTRY_LARAVEL_DSN`, `NEXT_PUBLIC_SENTRY_DSN` |
| Web analytics | Privacy-friendly traffic stats | M7 | Not implemented | e.g. Plausible/Umami (owner choice) | `NEXT_PUBLIC_ANALYTICS_*` |
| Newsletter (optional) | External list sync / campaign sending | M2 | Not implemented — the internal double opt-in list **is implemented**, with CSV export for importing into an email tool | Optional (Mailchimp/Brevo); keep sign-up OFF until transactional email is configured | provider API key |
| Have I Been Pwned | Breached-password check | M1 | **Implemented** (Laravel `uncompromised()` rule, enabled only when `APP_ENV=production`; never exercised against the live API yet) | None (k-anonymity range API, no key) | — |

## Per-integration documentation template

Each integration gets a section here (and setup steps in `docs/INTEGRATION_SETUP.md`, Milestone 9) covering:

- Required account · required credentials · environment variables
- Supported operations · webhook URL, events and signature method
- Failure handling · retry behaviour (backoff, max attempts, dead-letter) · idempotency key
- Test procedure (sandbox) · production verification requirement (who runs it, how it is evidenced)
- Evidence log: date, environment, operation, provider reference, result

## Evidence log

| Date | Environment | Integration | Operation | Result |
|---|---|---|---|---|
| 2026-10-14 | local | First-party CAPTCHA | Issue + solve + verify via website proxy | Pass |

_No third-party service has been tested against its real (sandbox or live) endpoint yet._
