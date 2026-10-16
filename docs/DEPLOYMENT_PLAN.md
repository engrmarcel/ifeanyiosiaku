# Deployment Plan

Status: **planning only**. Hosting decided 2026-10-15: **Hostinger VPS (KVM 2 or larger)**. Domain ownership and DNS access are not yet confirmed.
Nothing has been deployed. The detailed, step-by-step runbook (`docs/PRODUCTION_DEPLOYMENT.md`) is written in
Milestone 9 for the confirmed host.

## 1. Runtime requirements

| Component | Requirement | Why |
|---|---|---|
| PHP | 8.3+ with FPM; extensions: pdo_mysql, mbstring, intl, bcmath, gd or imagick, sodium, zip, fileinfo, curl, openssl, redis (phpredis) | Laravel + money + image processing |
| Node.js | 22 or 24 LTS, **long-running process** (or a platform that runs Next.js natively) | Next.js server rendering, ISR, image optimisation |
| MySQL | 8.4 LTS (8.0 acceptable) | Primary database |
| Redis | 7.x | Queues, cache, locks, rate limits |
| Process manager | Supervisor/systemd for queue workers and Next.js; PM2 acceptable for Node | Background jobs must run continuously |
| Cron | Every minute `php artisan schedule:run` | Scheduled publishing, reminders, snapshots, expiry, reconciliation |
| Web server | Nginx (or Caddy) reverse proxy, HTTPS (Let's Encrypt) | TLS, routing, headers |
| Storage | S3-compatible object storage (public + private buckets) | Media, paid files, backups |
| Outbound HTTPS | To payment, email, video, CAPTCHA providers | Integrations |
| Inbound HTTPS | Public webhook endpoints | Payment/Calendly webhooks |
| Resources | ≥ 2 vCPU, 4 GB RAM, 40 GB SSD to start | PHP-FPM + Node + MySQL + Redis on one host |

## 2. Hosting options assessment

| Option | Fit | Notes |
|---|---|---|
| **A. Single Linux VPS** (Hetzner, DigitalOcean, Linode/Akamai, Vultr, AWS Lightsail) — optionally managed with Laravel Forge or Ploi | ✅ **Recommended** | Runs everything; predictable cost; full control. Requires server maintenance (Forge/Ploi reduce this). |
| **B. Next.js on Vercel + Laravel on VPS/Laravel Cloud** | ✅ Good | Best Next.js experience; two platforms to manage; API must be reachable at a public origin, so the same-origin rewrite proxies via Vercel to the API host (ADR-003 still holds). |
| **C. Shared / cPanel hosting** | ❌ Not recommended | Usually no persistent Node process, no Supervisor workers, no Redis, limited cron. Some cPanel plans offer "Node.js app" + cron; would need verification of each requirement before considering. |
| **D. Containers (Docker on VPS / Fly.io / Render)** | ✅ Possible | Docker not installed locally; adds a learning curve for the owner. Could be added later via `/infra`. |

### 2.1 Hostinger (owner's chosen provider, 2026-10-14)

Researched 2026-10-14 from Hostinger's published pages and independent benchmarks; plan details and prices change
often and must be re-checked on hostinger.com before purchase. **Owner decision (2026-10-15): VPS, KVM 2 or larger.**

| Requirement (§1) | Shared "Premium" | Shared "Business" / Cloud (Startup+) | VPS (KVM 2 or larger) |
|---|---|---|---|
| PHP 8.3 + Laravel | ✅ | ✅ | ✅ (self-installed) |
| Node.js process for Next.js | ❌ | ✅ "Node.js Web Apps" (Node 18–24; Next.js listed as supported) | ✅ |
| MySQL | ✅ (MySQL/MariaDB, version to confirm) | ✅ (version to confirm) | ✅ MySQL 8.4 |
| Cron (`schedule:run` every minute) | ✅ | ✅ | ✅ |
| Persistent queue workers (Supervisor) | ❌ | ❌ — workaround: cron runs `queue:work --stop-when-empty` each minute (emails delayed up to ~1 min) | ✅ |
| Redis | ❌ | ❌ (database drivers instead) | ✅ |
| Private object storage | External (e.g. Cloudflare R2) | External | Local disk or external |
| Fit | **Not suitable** | **Workable for launch with compromises** | **Recommended — matches the architecture exactly** |

Published figures (indicative only): Business plan ≈ 2 CPU / 3 GB RAM; Cloud Startup ≈ 4 CPU / 4 GB RAM;
VPS KVM 2 = 2 vCPU / 8 GB RAM / 100 GB NVMe, roughly US$9–17/month on long terms, renewing higher.

**Recommendation:** Hostinger **VPS KVM 2** (Ubuntu LTS) using Option A's topology below. It runs the queue workers,
Redis, scheduler and Next.js server continuously, and leaves headroom for video/course traffic.

**If the owner already has a Business or Cloud plan:** deploy Laravel as a PHP site on `api.<domain>` and Next.js as
a Node.js Web App on `www.<domain>`, with `API_INTERNAL_URL=https://api.<domain>`. Queues and cache use the
`database` drivers and a cron-driven worker. That's acceptable for launch volumes; plan a move to a VPS before heavy
course or event traffic. Both paths will be verified with a staging deployment in Milestone 9.

## 3. Topology (Option A)

```
Internet ──HTTPS──> Nginx (www.ifeanyiosiaku.online)
                     ├── /             → Next.js (127.0.0.1:3000, systemd/PM2)
                     ├── /api (Next rewrites →) Laravel PHP-FPM
                     ├── /admin         → Laravel PHP-FPM (Filament)
                     └── /webhooks/*    → Laravel PHP-FPM
                    MySQL 8.4 (localhost only) · Redis (localhost, password) · Supervisor: queue workers ×2
                    cron: schedule:run · Object storage + video provider: external
apex ifeanyiosiaku.online → 301 → www
```

## 4. Environments and pipeline

| Stage | Trigger | Actions |
|---|---|---|
| CI | Every push / PR | Install, lint, typecheck, unit + feature tests (MySQL 8.4, Redis), `composer audit`, `npm audit`, build |
| Staging | Merge to `main` | Deploy to `staging.<domain>` with sandbox credentials; run migrations; smoke tests; Playwright journeys |
| Production | Manual approval / tag | Backup DB → zero-downtime deploy (release directories + symlink) → `migrate --force` → `db:seed --class=RolesAndPermissionsSeeder --force` (syncs new permissions) → cache config/routes/views → restart workers → health check → automatic rollback on failure |

## 5. Production readiness gates (must all be true before launch)

- [ ] Hosting confirmed and requirements in §1 verified
- [ ] Domain ownership confirmed; DNS (A/AAAA/CNAME), HTTPS, HSTS
- [ ] Email domain authentication (SPF, DKIM, DMARC) and deliverability test
- [ ] Payment providers: live keys, webhook URLs registered, a real live transaction succeeded and was refunded
- [ ] Video provider configured with token authentication
- [ ] Private bucket verified as not publicly listable/readable
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, secure cookies, trusted proxies, CSP enforced
- [ ] Staff two-factor requirement is ON (default in production, ADR-026): a staff account without 2FA is redirected away from `/admin`
- [ ] Queue workers and scheduler running and monitored (heartbeat)
- [ ] Encrypted daily backups + a **tested restore**
- [ ] Error monitoring receiving events
- [ ] Legal pages approved by owner (privacy, terms, refunds, reward-credit terms)
- [ ] Milestone 8 test and security pass complete
- [ ] Website `NEXT_PUBLIC_SITE_URL` set to the public https:// address (used in the sitemap, robots.txt and search-engine data); "Let search engines list this website" is ON for the live site and OFF on any test copy
- [ ] Web server serves `/media` with `X-Content-Type-Options: nosniff`, the sandbox CSP, and `Content-Disposition: attachment` for `.pdf` files (same headers as MediaFileController)
- [ ] Scheduler runs `content:announce-scheduled` every minute (scheduled articles, publications and projects appear on time)
- [ ] After each deploy: `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (adds new permissions such as contact.manage and newsletter.manage)
- [ ] Site settings: contact-form recipient email set; newsletter sign-up switched on **only** after transactional email is configured and tested
- [ ] PHP `intl` extension enabled (money formatting, e.g. ₦12,500.00)
- [ ] Private `products` storage (paid files) is outside the web root, never served by Nginx, included in backups; production uses a private S3-compatible bucket (ADR-010)
- [ ] PHP `upload_max_filesize` / `post_max_size` and Nginx `client_max_body_size` allow product uploads (up to 512 MB)
- [ ] Starting content installed once: `php artisan db:seed --class=InitialContentSeeder --force` (safe to re-run; never overwrites edits; creates the homepage and the /contact page if missing)

## 6. Backups (summary — full procedure in BACKUP_AND_RECOVERY.md, M9)

- Database: nightly `mysqldump --single-transaction` → compressed → encrypted (age/GPG) → off-site bucket; 30 daily + 12 weekly retained.
- Object storage: bucket versioning / replication for private bucket.
- Restore drill performed before launch and quarterly afterwards; result recorded.

## 7. Local development (this machine)

- XAMPP PHP 8.3 + MariaDB 10.4 (upgrade to MySQL 8.4 recommended), Node 24.
- Laravel served with `php artisan serve` (port 8000); Next.js `npm run dev` (port 3000) rewriting to it.
- Queue: `php artisan queue:work` (database driver). Mail: `log` mailer (or Mailpit if installed).
- Project lives in `htdocs`, but Apache is **not** used to serve it, to avoid exposing `.env` or `storage/`
  through `http://localhost/ifeanyiosiaku/...`. A deny-all `.htaccess` will be placed at the repo root as a safeguard.
