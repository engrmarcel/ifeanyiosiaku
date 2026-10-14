# Security Threat Model

Status: **planned controls** — none are implemented yet. Each control is verified by tests listed in the roadmap
before its milestone is marked complete. Method: assets → actors → trust boundaries → STRIDE threats → controls.

## 1. Assets

| Asset | Sensitivity |
|---|---|
| Payment flows (orders, payments, refunds) | Critical (financial) |
| Reward ledger and balances | High (financial liability) |
| Paid content: files, videos, premium articles, course lessons | High (revenue) |
| Accounts, sessions, staff/admin access | Critical |
| Personal data: names, emails, booking notes, IPs, submissions | High (privacy law) |
| Provider secrets (payment, email, video, CAPTCHA, storage) | Critical |
| CMS content integrity and brand reputation | High |
| Event meeting links | Medium |

## 2. Actors

Anonymous visitor · authenticated member · malicious member · compromised staff account · bots/spammers ·
payment-fraud actor · webhook forger · insider with DB access · supply-chain (dependency) attacker.

## 3. Trust boundaries

1. Browser ↔ Next.js (public internet).
2. Next.js ↔ Laravel (internal network/proxy; Laravel trusts only configured proxy IPs for forwarded headers).
3. Laravel ↔ MySQL / Redis / object storage (private network, least-privilege credentials).
4. Laravel ↔ external providers (outbound HTTPS; inbound signed webhooks).
5. Staff admin panel ↔ Laravel (elevated privilege boundary; 2FA).
6. Trusted raw-HTML blocks ↔ site origin (sandboxed iframe boundary).

## 4. Threats and controls

| # | Threat (STRIDE) | Controls | Verified by |
|---|---|---|---|
| T1 | **SQL injection** (T) | Eloquent/query builder bindings only; no raw SQL with interpolated input; sort/filter fields allow-listed | Static analysis rule; tests with injection payloads on filter/sort params |
| T2 | **Stored/reflected XSS** (T/I) | React escaping by default; Markdown via rehype-sanitize allow-list; rich text sanitised server-side (HTML Purifier) on save **and** rendered through allow-listed renderer; raw HTML only in sandboxed iframe without `allow-same-origin`; nonce-based CSP; no `dangerouslySetInnerHTML` outside the sanitiser wrapper (lint rule) | XSS payload test suite on posts, comments, articles, blocks, profile fields |
| T3 | **CSRF** (T) | Laravel CSRF token (XSRF-TOKEN cookie + X-XSRF-TOKEN header) on all state-changing browser requests; SameSite=Lax cookies; webhooks are CSRF-exempt but signature-verified | Feature tests: POST without token → 419 |
| T4 | **Broken access control / IDOR** (E/I) | Policies on every model action; scoped queries (`$user->orders()`); ULIDs; admin routes behind `can:` middleware + 2FA; deny-by-default | Per-resource "other user cannot read/modify" tests for orders, downloads, enrolments, bookings, tickets, submissions, certificates (private data) |
| T5 | **SSRF** (I) | No user-supplied URL fetching except approved embeds (oEmbed allow-list of hosts: YouTube, Vimeo, etc.); outbound HTTP client blocks private/link-local IP ranges and non-HTTPS; webhook targets not user-configurable | Tests with `http://169.254.169.254`, `localhost`, DNS-rebind style hosts |
| T6 | **File upload abuse** (T/D/E) | MIME sniffing + extension allow-list per context; size limits; images re-encoded (strips EXIF/polyglots); SVG disallowed for members (sanitised for staff); random storage names; private disk outside web root; never executed; served with `Content-Disposition` and `X-Content-Type-Options: nosniff`; optional ClamAV scan in production | Upload tests: PHP file renamed .jpg, oversized file, SVG with script |
| T7 | **Credential attacks** (S) | Password hashing (argon2id or bcrypt cost ≥ 12); min length 10 + breached-password check (HIBP k-anonymity, optional); login throttling per email+IP; CAPTCHA after failures; generic error messages; email verification; 2FA (mandatory for staff) | Throttle tests; enumeration tests on login/reset |
| T8 | **Session theft / fixation** (S) | Secure, HttpOnly, SameSite cookies; session ID regenerated on login/privilege change; idle (2 h) and absolute timeouts; shorter for admin; session list + revoke; logout invalidates server session; suspension kills all sessions | Tests: session regenerated; suspended user's session rejected |
| T9 | **Webhook forgery** (S/T) | HMAC signature verification over raw body with constant-time compare; reject unsigned; then **server-side verify call** to provider before fulfilment; optional provider IP allow-list | Tests: bad signature → 401 and no state change; valid signature but verify-call mismatch → no fulfilment |
| T10 | **Payment replay / amount tampering** (T) | Prices recomputed server-side; reference unique per attempt; amount + currency + reference checked against verify response; unique constraints (DATABASE_DESIGN §3); webhook idempotency | Tests: replayed webhook, mismatched amount, other order's reference |
| T11 | **Coin double-spend / race conditions** (T) | Row locks, balance − reserved checks, idempotency keys, single transaction per checkout | Concurrency test: parallel checkouts on one balance → at most one succeeds |
| T12 | **Reward farming / spam** (T/D) | Per-policy caps, cooldowns, eligibility (verified email, account age), duplicate-content hash, helpful votes ignored from new/related accounts and self-votes, budgets, suspicious-activity report, manual review threshold | Tests: repeated event → single credit; cap enforcement |
| T13 | **Automated abuse of forms** (D) | CAPTCHA abstraction (ADR-013), honeypot, min submit time, rate limits (Redis) per IP/user/route | CAPTCHA test matrix (valid/invalid/expired/replay/provider down/rate limit) |
| T14 | **Unrestricted resource consumption** (D) | Pagination caps (max 100), request body size limits, query timeouts, upload limits, rate limits, queue for heavy work, image processing limits, no code execution | Tests on page-size caps; load test before launch |
| T15 | **Sensitive data exposure** (I) | API Resources expose explicit fields only; leaderboard reads public snapshot table; secrets in env only; encrypted columns for 2FA secrets and meeting URLs; logs scrubbed; error pages without stack traces in production; private pages `no-store` + `noindex` | Snapshot/JSON-shape tests for public endpoints; config test `APP_DEBUG=false` in prod |
| T16 | **Paid-content leakage** (I) | Entitlement check for every download/lesson; signed short-lived URLs; private buckets; video signed playback; premium articles rendered server-side only for entitled users (no full body in public HTML/JSON) | Tests: unauthenticated & unentitled access to file, lesson, premium article |
| T17 | **Compromised staff account** (E) | Mandatory 2FA, least-privilege roles, audit log, re-authentication for sensitive actions (refunds, role changes, integration settings, raw HTML), admin session timeout, alerts on role changes | Tests: sensitive actions require recent password confirmation |
| T18 | **Malicious raw HTML by trusted admin / account takeover** (T) | `cms.raw_html` permission limited to Super Admin by default; sandboxed iframe; audit + revision diff | Test: iframe has no `allow-same-origin`; permission enforced |
| T19 | **Open redirect** (S) | Redirect targets (login `next`, announcement CTA, checkout return) validated against allow-list/relative paths | Tests with external URLs |
| T20 | **Supply-chain** (T) | Lockfiles committed; `composer audit` and `npm audit` in CI; Dependabot/Renovate; minimal dependencies; pinned CDN-free assets | CI step |
| T21 | **Repudiation of staff/financial actions** (R) | Append-only audit log with actor, IP, request ID; DB grants prevent UPDATE/DELETE on audit and ledger tables in production | Test: model guards throw on update/delete |
| T22 | **Insider DB access** (I/T) | Separate DB users (app, migrations, read-only reporting); encrypted backups; secrets not in DB | Ops checklist |
| T23 | **Email header injection / spoofing** (S) | Mail via framework (no raw headers from input); SPF, DKIM, DMARC on domain | Deployment checklist |
| T24 | **Clickjacking** (T) | `frame-ancestors 'none'` (except preview route to admin origin) | Header test |

## 5. Security headers (target)

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload   (after HTTPS confirmed)
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-…' [captcha/video hosts]; style-src 'self' 'unsafe-inline';
  img-src 'self' data: [storage/CDN hosts]; frame-src [video host] [captcha host]; connect-src 'self' [provider hosts]; frame-ancestors 'none'; base-uri 'self'; form-action 'self' [payment hosts]
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```
CSP is tuned and tested per milestone as third-party hosts are added (Report-Only first, then enforced).

## 6. Privacy

- Collect only what each feature needs (no date of birth, address only if invoices require it).
- Consent: newsletter double opt-in; cookie banner only if non-essential cookies/analytics are added (default:
  privacy-friendly analytics without cross-site tracking).
- Rights: data export (JSON) and account deletion request in account settings (see DATABASE_DESIGN §5).
- Legal review **required before launch**: privacy policy, terms of service, refund policy, cookie policy,
  reward-credit terms (closed-loop, no cash value), and compliance with the Nigeria Data Protection Act 2023 and
  any other jurisdictions where customers are targeted. This is flagged for the owner; engineering does not provide legal advice.

## 7. Secrets management and rotation

- `.env` never committed; `.env.example` contains names only.
- Production secrets in the host's secret store or a root-only `.env` (0600).
- Rotation procedure (documented in Milestone 9): generate new key at provider → update env → reload PHP-FPM/workers
  → verify → revoke old key. `APP_KEY` rotation uses Laravel's `APP_PREVIOUS_KEYS`.

## 8. Residual risks (accepted, documented)

- Screen recording of paid video cannot be prevented.
- Proof-of-work CAPTCHA does not stop well-resourced attackers.
- Purchased files can be re-shared after legitimate download (mitigation: licence terms, optional per-buyer watermarking in PDFs — P2).
