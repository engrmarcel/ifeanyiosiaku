# Architecture Decision Log

Each decision records context, the choice, and consequences. Status values: **Accepted**, **Proposed** (needs owner
confirmation before the milestone that depends on it), **Superseded**.

Plain-language summaries are given for the product owner.

---

## ADR-001 — Monorepo with a Laravel API and a Next.js website
**Status:** Accepted · 2026-10-14

**Context.** The repository is empty (greenfield). The brief specifies Laravel + Next.js.

**Decision.** One Git repository:

```
/apps/api     Laravel application (API, admin panel, queues, scheduler)
/apps/web     Next.js (TypeScript, App Router) public site, member/learner dashboards
/packages     (optional, later) shared TypeScript types generated from the API schema
/docs         Documentation
/infra        Deployment templates (Nginx, Supervisor, systemd, CI)
/scripts      Safe setup/test/maintenance scripts
```

**Consequences.** One place for code, docs and CI; two runtimes (PHP and Node) must be hosted.

> *Plain language:* the website people see and the "engine" behind it live in one project folder, in two clearly separated parts.

---

## ADR-002 — Modular monolith with domain modules
**Status:** Accepted

**Decision.** Laravel code is organised by domain under `app/Domain/<Module>` (Identity, Cms, Media, Publishing,
Commerce, Payments, Learning, Events, Challenges, Booking, Community, Rewards, Captcha, Notifications,
Announcements, Analytics, Integrations, Audit, Settings). Each module owns its models, services, actions, events,
policies and adapters. Cross-module communication goes through service interfaces and domain events, not by reaching
into another module's tables. No microservices.

**Consequences.** New modules can be added without redesign; modules could be extracted later if ever needed.

---

## ADR-003 — Same-origin API via Next.js rewrites + Laravel session cookies
**Status:** Accepted · amended 2026-10-14 during Milestone 1 (Sanctum removed)

**Context.** Browser auth must be secure (no tokens in localStorage), CSRF-protected and simple to host.

**Decision.**
- Browsers talk only to `www.<domain>`. Next.js rewrites `/api/*`, `/admin`, and the admin panel's assets
  (`/css|js|fonts/filament/*`, `/filament/*`, `/livewire-<hash>/*`) to the Laravel app (internal URL).
  Cookies are therefore first-party, `HttpOnly`, `Secure` (in HTTPS environments), `SameSite=Lax`.
- `/api/v1` routes are registered with Laravel's standard `web` middleware group (session + CSRF), the same group
  Fortify uses. CSRF uses the `XSRF-TOKEN` cookie + `X-XSRF-TOKEN` header; `GET /api/v1/csrf-cookie` primes it.
- **Amendment:** Sanctum was installed initially and then removed. With every browser request same-origin, the
  plain session guard is sufficient, and running Sanctum's stateful middleware alongside Fortify's `web` group
  would have meant two session/CSRF code paths. Sanctum can be re-added if third-party API tokens are ever needed.
- `APP_URL` is the **public** origin (e.g. `http://localhost:3100`), so signed links in emails and generated
  asset URLs match what the browser sees. Laravel trusts `X-Forwarded-*` only from `TRUSTED_PROXIES`.
- Next.js server components call the Laravel API server-to-server for public, cacheable content.
- Laravel Fortify (headless) provides registration, verification, password reset and TOTP 2FA endpoints.
- Webhooks (payments, Calendly) hit Laravel directly at `api.<domain>/webhooks/...` or via the same proxy path —
  documented per environment.

**Consequences.** Minimal CORS surface; requires the proxy to forward client IP headers (trusted proxies configured).

---

## ADR-004 — Role/permission model with spatie/laravel-permission
**Status:** Accepted

**Decision.** Use `spatie/laravel-permission` (roles, permissions, multiple roles per user). Laravel Policies wrap
every resource; controllers never check role names directly. A Super Administrator role is guarded so it cannot be
removed from the last holder.

---

## ADR-005 — Money stored as integer minor units + ISO currency
**Status:** Accepted

**Context.** The brief asks for "appropriate monetary decimal types". Paystack and Flutterwave exchange amounts in
minor units (kobo/cents) or decimals depending on endpoint.

**Decision.** All cash amounts are `BIGINT UNSIGNED` minor units (e.g. ₦12,500.00 → `1250000`) plus a `CHAR(3)`
currency column. Exchange/conversion rates use `DECIMAL(18,8)`. A `Money` value object (brick/money) performs all
arithmetic; floats are never used. Provider adapters convert at the boundary and verify the exact amount.

**Consequences.** Exact arithmetic, no rounding drift, matches provider APIs. This satisfies the "exact monetary
type" intent; it deviates from literally using `DECIMAL` for amounts, deliberately.

---

## ADR-006 — Reward coins are a separate, append-only ledger; leaderboard points are separate from coins
**Status:** Accepted

**Decision.**
- `reward_ledger_entries` is insert-only (DB user lacks UPDATE/DELETE on it in production; model guards in code).
  Corrections are compensating entries referencing the original.
- `reward_accounts.balance` is a cached projection updated in the **same transaction** as the ledger insert, under
  `SELECT ... FOR UPDATE`. A nightly job re-derives balances from the ledger and alerts on drift.
- Every credit has a unique `idempotency_key` (e.g. `course_completed:{user}:{course}`), enforced by a unique index.
- **Leaderboard points** are recorded in a separate `engagement_point_entries` table. Spending coins must not drop a
  member's ranking, and ranking rules may weight activities differently from coin rewards.

> *Plain language:* coins are like store credit with a bank-statement-style history that can never be edited, only
> corrected with a new line. Leaderboard score is a separate tally so spending coins doesn't cost anyone their rank.

---

## ADR-007 — Block content as versioned, validated JSON
**Status:** Accepted

**Decision.** Page content is an ordered list of blocks `{id, type, version, props, visibility}` stored per page
revision. Each block type has a schema (PHP validator on save; Zod schema on the frontend). The frontend renders via
a block registry (`type → React component`). Unknown or invalid blocks are skipped with a logged warning, never
rendered raw. Block schemas carry a `version`; migrations upgrade old block data.

---

## ADR-008 — Administration UI: Filament panel in Laravel
**Status:** Accepted · 2026-10-14 (owner accepted defaults)

**Context.** The admin must manage ~80 entity types. Building every admin screen by hand in Next.js would roughly
double front-end effort and delay revenue features.

**Decision (recommended).** Use **Filament** (Laravel admin framework) at `/admin` for back-office management
(CRUD, tables, filters, reports, settings, moderation queues, ledger views). Theme it with the same design tokens
(dark). The CMS page builder uses Filament's Builder field with block forms mirroring ADR-007 schemas; "Preview"
opens the real Next.js page in preview mode via a signed, short-lived preview token.

Public site, member, learner, checkout and community experiences remain in Next.js.

**Alternative.** Custom Next.js admin — maximal visual consistency, considerably more build and test time.

**Consequences.** Faster, well-tested admin served by the Laravel app.

**Implementation note (Milestone 1).** The panel has **no login page of its own**. Staff sign in on the website
(Fortify, with TOTP 2FA) and the same session cookie authorises `/admin`, because both live on one origin.
Signed-out visitors are redirected to `/login?next=/admin`; staff without confirmed 2FA are redirected to
`/account/security?setup=two-factor&next=/admin` (`RequireStaffTwoFactor`). This avoids two separate 2FA
enrolments (Filament's built-in MFA stores recovery codes differently from Fortify's). Verified end-to-end through
the proxy on 2026-10-14.

---

## ADR-009 — Queues, cache and rate limiting
**Status:** Accepted

**Decision.** Production: Redis for queues (Laravel Horizon optional), cache, locks and rate limiting.
Local development on this machine: `database` queue and cache drivers (Redis is not installed locally).
Code depends only on Laravel's queue/cache contracts, so drivers are configuration-only.

**Sessions stay on the `database` driver in every environment** (not Redis), so members can list and revoke
their sessions and suspension can delete them (`RevokeUserSessions`).

---

## ADR-010 — File storage
**Status:** Accepted

**Decision.** Two logical disks: `public` (optimised public images) and `private` (paid files, submissions,
receipts). Local: filesystem under `storage/app`. Production: S3-compatible object storage (e.g. Cloudflare R2,
Backblaze B2, AWS S3) with **private bucket** for protected files. Downloads are issued as short-lived signed URLs
only after an entitlement check, and each issuance is logged.

---

## ADR-011 — Video streaming provider via adapter
**Status:** Accepted default (Q5) · 2026-10-14 — revisit when an account is opened

**Decision.** `VideoProvider` interface (upload/ingest, status, signed playback URL, delete). First adapter:
**Bunny Stream** (token-authenticated playback, low cost) — alternatives Mux or Cloudflare Stream behind the same
interface. Paid lessons never expose a permanent file URL. Screen-recording cannot be fully prevented; this is
documented to the owner.

---

## ADR-012 — Payment provider adapters: Paystack and Flutterwave
**Status:** Accepted

**Decision.** `PaymentGateway` interface: `initialize(order)`, `verify(reference)`, `parseWebhook(request)`
(signature check: Paystack `x-paystack-signature` HMAC-SHA512 of raw body with secret key; Flutterwave
`verif-hash` / signature header per current docs — verified against official docs at implementation time),
`refund(payment, amount)`. All webhook payloads are stored in `webhook_events` with a unique
`(provider, provider_event_id)` constraint before processing (idempotent). Fulfilment only follows a server-side
verify call that confirms status, amount and currency.

Live verification stays **pending** until real credentials and a production test transaction are supplied.

---

## ADR-013 — First-party CAPTCHA: signed proof-of-work challenge
**Status:** Accepted

**Context.** Must be accessible, not trivial arithmetic, server-validated, single-use.

**Decision.** Server issues a challenge `{id, salt, difficulty, expires_at}` signed with HMAC; the browser solves a
SHA-256 proof-of-work in a Web Worker (invisible to the user, ~0.5–2 s on mobile; difficulty adaptive to risk).
Server verifies signature, expiry, solution, and marks the challenge id consumed (unique row / atomic cache `add`)
to prevent replay. Bound to form purpose and session. Combined with honeypot field, minimum-time-to-submit and
per-IP/per-account rate limits. No image puzzles → no visual-accessibility barrier. reCAPTCHA and Turnstile are
alternative drivers behind `CaptchaVerifier`.

**Limitation.** Proof-of-work raises the cost of automated abuse; it does not stop a determined, well-resourced
attacker. Rate limits and moderation remain essential.

---

## ADR-014 — Database engine and local parity
**Status:** Accepted

**Context.** This machine runs XAMPP **MariaDB 10.4.32** (end-of-life since June 2024). Production target is
**MySQL 8.4 LTS** (or 8.0+ if the host only offers that).

**Decision.** Write migrations to the common subset of MySQL 8 and MariaDB 10.4 for now (no MySQL-only functional
indexes or `SKIP LOCKED` dependence). CI runs the test suite against **MySQL 8.4** to guarantee production parity.
Recommend upgrading local DB to MySQL 8.4 (or MariaDB 10.11 LTS) during Milestone 1. Dedicated databases:
`ifeanyiosiaku` and `ifeanyiosiaku_testing` — no other existing databases on this server are touched.

---

## ADR-015 — Identifiers
**Status:** Accepted

**Decision.** Internal `BIGINT` auto-increment primary keys; resources exposed in URLs or APIs where enumeration
would leak information (orders, payments, bookings, tickets, certificates, submissions, downloads) also carry a
`ulid` public identifier. Authorisation is still enforced on every access — the ULID is defence in depth, not access control.

---

## ADR-016 — Time handling
**Status:** Accepted

**Decision.** DB and server in UTC. Users and events store an IANA time zone. Default site time zone
`Africa/Lagos`. Booking slots are computed in the host's availability time zone and displayed in the viewer's.

---

## ADR-017 — Testing and quality tooling
**Status:** Accepted

**Decision.** Backend: Pest (PHPUnit), Larastan (PHPStan) level 8 target, Laravel Pint, `composer audit`.
Frontend: Vitest + Testing Library, ESLint, Prettier, TypeScript strict, `npm audit`, axe accessibility checks.
End-to-end: Playwright (Journeys A–F), payment journeys against provider sandboxes or recorded fakes clearly labelled as such.
CI: GitHub Actions (MySQL 8.4 + Redis services). Builds fail on test, type or lint errors.

---

## ADR-018 — Hosting target
**Status:** Accepted · 2026-10-15 — **Hostinger VPS, KVM 2 or larger** (owner decision). See DEPLOYMENT_PLAN.md §2.1

**Recommendation.** A Linux VPS (≥ 2 vCPU / 4 GB RAM) running Nginx, PHP-FPM 8.3+, Node 22/24 LTS, MySQL 8.4,
Redis, Supervisor (queue workers) and cron (scheduler); object storage and video provider external.
Typical shared/cPanel hosting cannot reliably run the Next.js server, queue workers and Redis and is **not**
recommended. Alternative: Next.js on Vercel + Laravel on a managed VPS (Forge/Ploi/Laravel Cloud).

---

## ADR-019 — Frontend stack details
**Status:** Accepted

**Decision.** Next.js (current stable; 16.x at time of writing) with App Router, React Server Components, TypeScript
strict, Tailwind CSS v4 with design tokens as CSS variables, Radix UI primitives (accessible) wrapped in our own
components, Motion (Framer Motion) for animation gated by `prefers-reduced-motion`, Shiki for server-side syntax
highlighting, react-markdown + rehype-sanitize for Markdown, TanStack Query for client-side data, Zod for schemas.

---

## ADR-020 — Framework and package versions
**Status:** Accepted · pinned 2026-10-14

**Decision.** Pinned via `composer.lock` / `package-lock.json`:

| Package | Version |
|---|---|
| laravel/framework | 13.36.0 (requires PHP ≥ 8.3) |
| laravel/fortify | 1.41.0 |
| filament/filament | 5.10.2 (Livewire 4) |
| spatie/laravel-permission | 8.3.0 |
| brick/money | 0.15.2 |
| pestphp/pest · larastan/larastan | 4.x · 3.x |
| next · react | 16.4.0 · 19.3.0 |
| tailwindcss · zod · motion | 4.x · 4.6.7 · 14.2.0 |
| vitest | 5.0.4 |

Local PHP is 8.3.33; production must be PHP ≥ 8.3 and Node ≥ 22.12.

---

## ADR-021 — `User` model location
**Status:** Accepted · 2026-10-14

**Decision.** `User` stays at `App\Models\User` (Laravel, Fortify, Filament and spatie/permission reference it by
convention). Every other model lives in its domain module (`App\Domain\<Module>\Models`). HTTP controllers and
middleware specific to a module live in `App\Domain\<Module>\Http`; cross-cutting middleware in `App\Http`.

---

## ADR-022 — Local ports
**Status:** Accepted · 2026-10-14

**Decision.** API `127.0.0.1:8110`, website `localhost:3100`. Ports 8000, 8090–8092 and 8100 are used by other
projects on the development machine.

---

## ADR-023 — Account enumeration resistance
**Status:** Accepted · 2026-10-14

**Decision.** Login failures return the same message for unknown emails and wrong passwords; the suspended-account
message is only shown after a correct password. "Forgot password" always returns the same success message
(`GenericPasswordResetLinkResponse`), overriding Fortify's default, which reveals unknown emails. Registration
still reports a duplicate email (a deliberate usability trade-off, protected by CAPTCHA and rate limits).

---

## ADR-024 — Content protection (image saving and Inspect)
**Status:** Accepted · 2026-10-15 (owner request)

**Context.** The owner asked that visitors not be able to save or download pictures or open "Inspect", controlled
from the admin panel and OFF by default.

**Decision.** Admin setting **"Allow visitors to save images and use Inspect"** (`protection.allow_saving_and_inspect`,
default `false`) on *System → Site settings*. While it is off, the public website:
- blocks the right-click menu (except inside form fields, so paste and autofill still work);
- blocks dragging images/videos out of the page and iOS long-press saving (`-webkit-touch-callout: none`);
- blocks F12, Ctrl+Shift+I/J/C/K, Ctrl+U, Ctrl+S and the macOS equivalents (⌘⌥I/J/C/U, ⌘U, ⌘S).

Protection is active while the setting loads and if it cannot be loaded (fail-safe). Signed-in staff are exempt so
the owner can debug. The admin panel is not affected.

**Limitation (explained to the owner).** This is a deterrent, not a security control. Browsers always keep their own
menu route to developer tools, screenshots work, disabling JavaScript removes the handlers, and any image shown on
screen has been downloaded by the browser. Copy and paste of text, keyboard navigation and screen readers are
deliberately left working (accessibility). Paid files remain protected server-side (entitlements and signed URLs,
Milestone 3).

---

## ADR-025 — Optional modules as switchable add-ons
**Status:** Accepted · 2026-10-15 (owner request)

**Decision.** Commerce, Learning, Community and Rewards are **add-ons**, each with an admin switch on
*System → Site settings* (`addons.<name>.enabled`, all **ON** by default, changes audited). The core platform
(portfolio/CMS, accounts, events, appointments, administration) is always on.

Every module built from Milestone 3 onward must:
- put its API routes behind `->middleware('addon:<name>')` — a disabled module answers **404** (`addon_disabled`), as if it did not exist;
- hide its website pages and navigation with `useAddon('<name>')` (and a server-side check on its pages);
- hide its admin screens when switched off;
- keep its data when switched off, so switching back on restores everything.

**Cross-module rules** (enforced as the modules are built): paid courses, paid tickets and paid bookings need
Commerce; spending Osiaku Coins at checkout needs both Rewards and Commerce. When a dependency is off, the
dependent feature degrades (e.g. free courses only) instead of failing.

---

## ADR-026 — Staff two-factor requirement defaults by environment
**Status:** Accepted · 2026-10-15 (owner request). Supersedes the "mandatory by default" wording in REQ-AUTH-5.

**Decision.** `security.require_staff_two_factor` defaults to **OFF** when `APP_ENV` is local, testing or staging,
and **ON** when `APP_ENV=production`. `REQUIRE_STAFF_TWO_FACTOR=true|false` in the server environment overrides
the default, and a Super Administrator can still change it in Site settings. Two-factor remains available to
every member at all times. The automated tests run with the requirement **on**, so the production behaviour stays
tested.

**Consequence.** Production deployment must set `APP_ENV=production` (already a launch gate); the deployment
checklist verifies that `/admin` demands two-factor for staff.

---

## ADR-027 — How CMS pages are served (status codes, caching, instant updates)
**Status:** Accepted · 2026-10-15

**Context.** Next.js 16 streams pages; once streaming starts the HTTP status cannot change, so a missing page
would return 200 (with a noindex tag) and a moved page would redirect client-side — both poor for search engines.

**Decision.**
- **Address check first:** the website's `proxy.ts` asks Laravel `GET /api/v1/page-resolve?path=` (cached, cleared on
  publish) before rendering any address that could be a CMS page. Moved pages get a real **301**; unknown
  addresses are rewritten to `/page-not-found`, which returns a real **404** with the styled not-found page. If the
  API is unreachable the request continues normally.
- **Rendering:** CMS pages render on the server per request (`instant = false`; homepage uses `connection()`), so
  builds never depend on the API being reachable. Page data is cached with `"use cache"` + `cacheTag("page:<path>")`
  (hourly lifetime as a safety net).
- **Instant updates:** publishing dispatches `RefreshWebsitePaths` (queued, 5 retries) which POSTs the changed paths to
  `/internal/revalidate` with the shared `WEB_REVALIDATE_SECRET` (≥ 32 chars, constant-time comparison). A refresh
  failure is logged and never blocks a publish.
- **Rate limiting:** server-to-server calls from the website (no `X-Forwarded-For`, from a trusted proxy address) are
  not rate limited; visitor requests through the proxy keep per-visitor limits.
- **Blocks on the website:** each block's props are validated with Zod; unknown/malformed blocks are skipped and
  logged. Rich text is inserted only through `SafeHtml` (API-sanitised HTML). Code is highlighted server-side with
  Shiki (no highlighting JavaScript shipped). Videos load from YouTube (privacy-enhanced domain) or Vimeo only after
  the visitor presses play.
- **Animation:** the terminal reveal is pure CSS, always ends visible, and is skipped for reduced motion. The Motion
  library was removed (no longer used), reducing JavaScript sent to visitors; ADR-019's animation choice is updated
  accordingly.

## ADR-028 — Site-wide navigation, appearance, redirects and search-engine data
**Status:** Accepted · 2026-10-15

**Context.** The owner needs to change the menu, footer, social links and colours without a developer, add
redirects for old or printed addresses, and have the site understood by search engines, all without risking
broken or unsafe links.

**Decision.**
- **Navigation & footer** (Admin → Content → Navigation & footer, `cms.manage`): header links (max 8), footer links
  (max 12), social profiles (max 10, only real accounts; starts empty), footer line and accent colour, stored as
  settings and audit-logged. Links must be site paths, `#anchors`, `https://`/`http://` or `mailto:` (same rule as
  blocks); anything else is dropped on save and again when served. A link can be tied to an add-on and is hidden
  while that add-on is off (ADR-025).
- **Accent colour is a preset, not free colour input.** Five presets, each verified by an automated contrast test
  (WCAG AAA, ≥ 7:1) on the site background and under button text. Values reach the website as CSS custom
  properties validated as `#rrggbb`, so no CSS can be injected. All glows derive from the accent variable.
- **Shared layout data:** the website reads `GET /api/v1/site` with `"use cache"` + tag `site`; Laravel refreshes it
  (`RefreshWebsitePaths` now also carries allow-listed tags) when navigation, theme, profile, site, SEO or add-on
  settings change. If the API is unreachable (e.g. during a build) the built-in defaults are cached for one minute
  only, then retried — verified locally: the build-time fallback replaced itself within about 10 seconds.
- **Manual redirects** (Admin → Content → Redirects; editing needs `cms.publish`): old address → site path or
  `https://` address, permanent (301) or temporary (302). Refused: website-owned addresses, the address of a live
  page (the page would always win), self-redirects and chains; redirects pointing at a newly redirected address are
  repointed straight to the final destination. Each use is counted. Old `/projects/…` addresses can be redirected.
- **Search engines:** `/sitemap.xml` lists published pages without `noindex` or an external canonical, plus
  published case studies; `/robots.txt` keeps private areas (admin, account, auth, preview, API) out. A Site
  settings switch "Let search engines list this website" (default ON) empties the sitemap, blocks all crawling in
  robots.txt and adds `noindex` to every page — intended for test copies. JSON-LD (`Person` + `WebSite` on the
  homepage, `CreativeWork` on case studies) is built only from real profile fields; it is serialised with `<`
  escaped so content cannot break out of the script tag (tested).
- **Social links are shown as text labels**, not brand logos, matching the terminal style and avoiding inaccurate
  hand-drawn logos.

## ADR-029 — Articles, publications, documents and sharing
**Status:** Accepted · 2026-10-16

**Context.** The owner writes technical content (code, data, dashboards) and publishes papers and eBooks with new
editions over time. Visitors should be able to share any of it easily.

**Decision.**
- **Articles are written in Markdown** (REQ-CMS-7), not page blocks: it suits code-heavy writing. The API renders
  Markdown with league/commonmark (now a declared dependency, ^2.10.3; versions with known advisories are
  ≤ 2.10.1), with raw HTML **stripped**, unsafe links dropped, and the result sanitised again with an allow-list.
  Top-level code blocks are sent separately and highlighted on the website by Shiki (never executed). `#` headings
  become section headings (the title is the page's only h1) and form the contents list. Images must come from the
  media library; any other image is replaced by its description, so articles cannot load trackers.
- **Publishing:** draft → signed preview link → published; a future publish time schedules it.
  `content:announce-scheduled` (every minute) tells the website when scheduled articles, publications **and
  projects** go live. That last case was a gap from step 4: future-dated projects would not appear until the
  hourly cache expired. Unlike pages, saving a published article updates the live site directly. Every save is kept
  (latest 50 versions) and any version can be restored; restoring never unpublishes. Without the publish permission
  a writer can create drafts but cannot publish or change live articles.
- **Addresses:** `/articles/{slug}`, `/publications/{slug}`. Renaming live content leaves a 301 from the old
  address (shared `AutomaticRedirects`, also used by pages). Pages cannot use `articles`/`publications` as their
  first segment; manual redirects may start from old item addresses in those sections.
- **Categories and tags** are shared tables with a `type` on categories (articles now; store, courses and events
  later). Search uses simple text matching for now (see DATABASE_DESIGN).
- **Publications** have versions; each needs a PDF from the media library and/or an https link (e.g. DOI page).
  Paid publications will be sold through the store (Milestone 3, private files).
- **PDFs in the media library:** signature and real type checked; files containing scripts, launch actions,
  embedded files, rich media or XFA forms are refused, including inside compressed streams and hex-escaped names
  (an ordinary "open at page 1" action is allowed). PDFs are always served as downloads with the sandbox CSP. They
  are stored unchanged, so document properties (author, software) remain; the upload screen says so. Image fields
  never show a document, even if one was stored by mistake.
- **Sharing:** one shared `ShareBar` (LinkedIn, X, Facebook, WhatsApp, Telegram, email, copy link, and the
  device's own share sheet where available) on article and publication pages, hidden in previews. Plain links to
  each network's share page, so **no third-party scripts, cookies or tracking** load on the site. Icons use the
  official simple-icons shapes (CC0); LinkedIn withdrew its logo from that set, so a simple "in" mark is drawn.
  Event, course and store item pages will use the same component when those modules are built (roadmap M3–M5).

## ADR-030 — Announcement popup, contact form and newsletter
**Status:** Accepted · 2026-10-16

**Context.** The owner wants to promote launches, receive enquiries and build a mailing list, without nuisance
popups, spam, or sending email on behalf of attackers, and with honest consent records.

**Decision.**
- **Announcements:** the server checks the master switch `announcements.enabled` (OFF by default) on every
  request, so nothing shows while it is off, even active or scheduled announcements (REQ-ANN-1, tested on both
  sides). The highest-priority running announcement for the visitor (audience) and page (placement) is shown in
  the accessible Radix dialog. It never appears on admin, account, sign-in, preview or newsletter pages.
  "How often" is remembered only in the visitor's browser (no cookies), and editing the text shows it again.
  Statistics use a random browser id that the server stores only as a keyed hash. Reloads within 30 minutes are
  not counted twice, and raw events are deleted after 13 months. Creating or editing announcements needs the
  publish permission and is audit-logged. Button links use the same safe-link rule as page blocks (T19).
- **Contact form:**
  - It is a page section; a `/contact` page is seeded and "Contact" is added to the default menu.
  - CAPTCHA, plus limits of 3 per 10 minutes and 20 per day per visitor. Line breaks in the name or subject are
    refused (email header injection), and messages with many links are stored as spam and not emailed.
  - Messages always land in Inbox → Contact messages (new `contact.manage` permission, Admins).
  - They are also emailed to the address set in Site settings, with Reply-To set to the sender. The sender's
    text appears literally, so it cannot become links or formatting in the owner's inbox.
  - **No automatic reply**, so the form cannot be used to send our email to strangers.
  - IP addresses are kept only as keyed hashes; messages are deleted after 24 months.
- **Newsletter:**
  - Sign-up is **OFF by default** (`newsletter.enabled`), because confirmation emails need a real email
    provider (none configured yet).
  - Double opt-in: the emailed link (signed, valid 7 days, replaced by any newer email) opens a page where the
    person **presses a button**, so email security scanners that open links cannot subscribe anyone.
  - The response is identical whether or not an address is already listed. Confirmations are resent at most
    every 10 minutes, with a per-address daily limit.
  - Each sign-up stores the consent wording, source and time. Unsubscribe links never expire and work even with
    sign-up off.
  - Admins (new `newsletter.manage` permission) can view, erase and export confirmed subscribers to CSV, with
    spreadsheet formulas neutralised and each export audit-logged.
  - Sending campaigns and Mailchimp/Brevo sync are not built (see INTEGRATION_REGISTER).
- Email delivery logs now also record (hashed) addresses that are not accounts.

## ADR-031 — Custom code, downloads and diagram blocks; quality fixes
**Status:** Accepted · 2026-10-16

**Context.** REQ-CMS-8 asks for trusted HTML/CSS/JS on pages. Running such code inside the site would let it read
visitors' sessions or change the page. Diagrams and downloads were the remaining planned blocks. Milestone 2's
acceptance requires measured Lighthouse/axe results.

**Decision.**
- **Custom code is never inserted into the page.**
  - The block's props carry only a signed address `/api/v1/embeds/{token}`. The token names the page, the
    block's position, a fingerprint of the code, and for drafts a 15-minute expiry.
  - Laravel serves exactly that code as its own document, with `Content-Security-Policy: sandbox allow-scripts
    allow-popups allow-forms …; frame-ancestors 'self'` and `X-Frame-Options: SAMEORIGIN`; every other response
    still refuses framing.
  - The website shows it in an `<iframe sandbox>` without `allow-same-origin`.
  - Result, verified in Chrome while signed in as a Super Administrator: the code runs with origin `null` and
    cannot read cookies, storage, the parent page or `/api/v1/me`.
  - "Fit content" height is reported by `postMessage`; the website accepts it only from that frame and clamps it.
  - Changing or removing the code invalidates old addresses.
- **Only staff with `cms.raw_html` (Super Administrators) may add or change custom code.**
  `SavePageDraft` compares content fingerprints with the previous draft. Other editors may still move, hide or
  remove the block and edit the rest of the page. Every change is audit-logged (`page.raw_html_changed`).
- **Downloads block:** PDFs from the media library (usage-protected), with file sizes. Paid files go through the
  store (M3).
- **Diagram block:** Mermaid text, drawn in the browser in Mermaid's `strict` security level; `%%{init}`
  directives are refused. A required text description is the accessible name and the fallback. The library loads
  only when a diagram is near the screen.
  - Versions: mermaid 12.1.0 (not the three-day-old 12.2.0), with an npm override pinning KaTeX to 0.18.11, which
    fixes a low-severity advisory; `npm audit` is clean.
- **Quality fixes found by measurement** (QUALITY_REPORT.md):
  - Visitors no longer trigger a console 401 on every page: a new public `GET /api/v1/session` returns
    `{data: null}`, while `/me` keeps refusing visitors.
  - Zod runs `jitless`, so there are no CSP `eval` reports.
  - The code font is not preloaded, giving the hero image priority.
  - The header no longer ships the validation library to browsers.
- **Hero motion:** CSS-only grid drift and floating code glyphs, on large screens only, disabled for reduced motion.
