# Product Requirements — IO Digital Ecosystem

| | |
|---|---|
| **Public brand** | Ifeanyi Osiaku |
| **Proposed domain** | `https://www.ifeanyiosiaku.online` (configurable; ownership/DNS not yet confirmed) |
| **Status** | Milestone 0 — planning. Nothing described here is implemented yet. |
| **Owner** | Product owner (non-developer). Engineering decisions are recorded in [DECISIONS.md](DECISIONS.md). |
| **Last updated** | 2026-10-14 |

Requirement IDs (`REQ-<area>-<n>`) are referenced by the roadmap, tests and acceptance criteria.
Priority: **P0** = required for first public launch, **P1** = required for the full ecosystem, **P2** = later/optional.

---

## 1. Vision

A single, premium platform that presents Ifeanyi Osiaku's professional identity and generates revenue from
education (courses), digital products (eBooks, research, templates, code), professional services (appointments),
events (seminars, workshops, coding challenges) and a gamified learning community — all managed from one
administration area without code changes.

### Business goals

1. Establish a credible, distinctive professional identity online.
2. Sell courses, digital products, tickets and paid consultations with verified payments.
3. Build a returning community through learning, events, achievements and a public leaderboard.
4. Let the owner run everything (content, layout, products, rules, announcements) without a developer.

### Success measures (to be baselined after launch, not invented now)

- Paid orders per month, revenue per channel (courses / products / events / bookings).
- Course enrolment → completion rate.
- Booking conversion (service page view → confirmed booking).
- Monthly active community members; returning-visitor rate.
- Refund rate and payment-failure rate.

## 2. Users and roles

| Persona | Description | Key needs |
|---|---|---|
| **Visitor** | Anonymous public user | Discover profile, portfolio, content, products, courses, events; book; buy |
| **Member / Learner** | Registered, verified account | Purchases, downloads, courses, progress, certificates, community, rewards |
| **Customer** | Member with at least one order (not a separate role) | Order history, receipts, refunds requests |
| **Judge** | Assigned per coding challenge | Score submissions, comment |
| **Moderator** | Community staff | Review reports, remove content, restrict members |
| **Editor** | Content staff | CMS pages, articles, publications, media |
| **Instructor** | Course staff (initially the owner) | Author courses, grade assignments |
| **Finance** | Optional staff role | Orders, refunds, reconciliation, financial reports |
| **Administrator** | Full operational control | Everything except super-admin-only security settings |
| **Super Administrator** | Owner | Roles, integrations, security settings, raw-HTML permission |

Roles are bundles of fine-grained permissions (e.g. `orders.refund`, `cms.publish`, `cms.raw_html`). A user can hold
several roles. All checks are server-side.

## 3. Brand profile (initial CMS content)

Seeded as **editable draft content**, sourced only from the supplied instructor-profile artwork and the brief:

- **Name:** Ifeanyi Osiaku
- **Headline:** Software Developer | AI Enthusiast | Business Intelligence & Data Analytics Professional
- **Experience:** 12 years (editable number field; displayed only where the owner chooses)
- **Expertise:** Software Development, Artificial Intelligence, Business Intelligence, Data Analytics, Data Visualization
- **Technologies:** Python, Qlik, Microsoft Power BI, Tableau
- **Positioning copy:** "Ifeanyi Osiaku is a technology professional focused on software development, artificial
  intelligence, business intelligence, data analytics, and data visualization. His work and educational content
  emphasize turning technical knowledge and complex data into practical solutions to real-world problems."

Rules:

- REQ-BRAND-1 (P0): No employers, qualifications, certifications, clients, awards, outcomes or revenue figures are
  invented. Empty sections are hidden, not filled with placeholder claims.
- REQ-BRAND-2 (P0): Third-party/training brands seen in the artwork (e.g. the training programme's logo and
  organisation names) are **not** hard-coded. They can be added as portfolio projects/programmes via the CMS.
- REQ-BRAND-3 (P0): All profile fields, photos and links are CMS content; uploaded assets carry alt text,
  description and usage references in the media library.
- **Open question Q1:** the artwork presents the name as "Marcel Ifeanyi". Confirm the preferred public form
  (e.g. "Ifeanyi Osiaku" vs "Ifeanyi Marcel Osiaku"). Default: "Ifeanyi Osiaku".
- **Open question Q2:** a clean, high-resolution portrait (no poster text/graphics) is needed for the hero. The
  supplied artwork is a composed flyer; cropping it will look poor at large sizes.

## 4. Functional requirements by module

### 4.1 Identity & access (Milestone 1)

- REQ-AUTH-1 (P0) Registration with email verification; CAPTCHA-protected.
- REQ-AUTH-2 (P0) Login/logout; session-based, HttpOnly secure cookies; throttled.
- REQ-AUTH-3 (P0) Password reset via single-use, expiring token.
- REQ-AUTH-4 (P0) Session management: list active sessions, revoke others.
- REQ-AUTH-5 (P1) Optional TOTP multi-factor authentication with recovery codes; **mandatory for staff roles** (default, configurable).
- REQ-AUTH-6 (P0) Account suspension and immediate revocation of sessions/staff access.
- REQ-AUTH-7 (P0) Role-based access control with permissions; server-side policies on every protected operation.
- REQ-AUTH-8 (P1) Account deletion request workflow (with financial-record retention, see privacy section).

### 4.2 Portfolio & CMS (Milestone 2)

- REQ-CMS-1 (P0) Editable pages: Home, About, Portfolio, Projects/Case studies, Services, Experience, Skills,
  Certifications, Testimonials, Articles, Publications/Research, Contact, custom pages.
- REQ-CMS-2 (P0) Draft → preview → publish; scheduled publish/unpublish.
- REQ-CMS-3 (P0) Revision history with restore.
- REQ-CMS-4 (P0) Block-based page builder (rich text, headings, lists, tables, image, gallery, video/approved
  embeds, downloads, callout, quote, tabs, accordion, cards/grid, two-column, stats, timeline, diagram, code block,
  terminal panel, section templates). Blocks reorderable, duplicable, hideable.
- REQ-CMS-5 (P0) Homepage sections (15 listed in the brief) are blocks that can be reordered, edited, hidden,
  shown, duplicated and configured.
- REQ-CMS-6 (P0) Code blocks: language (HTML, CSS, JS, TS, PHP, Python, SQL, JSON, Bash + configurable), title,
  line numbers, copy button, highlighted lines, annotations. Highlighting happens at render time; code is never executed.
- REQ-CMS-7 (P1) Markdown authoring and technical article layout (TOC, reading time, syntax highlighting).
- REQ-CMS-8 (P1) Trusted raw HTML/CSS/JS block — requires `cms.raw_html` permission, rendered isolated in a
  sandboxed iframe, audit-logged.
- REQ-CMS-9 (P0) Media library: upload, alt text, description, usage references, image variants.
- REQ-CMS-10 (P0) Slugs with automatic redirects on change; manual redirects.
- REQ-CMS-11 (P0) SEO: meta title/description, Open Graph image, canonical URL, robots per page, sitemap.xml,
  robots.txt, JSON-LD (Person, Article, Course, Event, Product, Breadcrumb).
- REQ-CMS-12 (P0) Configurable header, footer, navigation, social links, branding tokens.
- REQ-CMS-13 (P1) Search and filtering across published content.

**Acceptance:** an administrator creates a page, adds/reorders blocks, saves a draft, previews, publishes, and sees
it on the public site with correct metadata — without touching source files (Journey F).

### 4.3 Commerce (Milestone 3)

- REQ-COM-1 (P0) Product catalogue: eBook, PDF, research paper, publication, template, code package, digital asset,
  premium article, course enrolment, event ticket, appointment service, admin-defined types.
- REQ-COM-2 (P0) Categories, tags, media, previews, pricing per currency, free products, search/filters, related products.
- REQ-COM-3 (P1) Coupons, promotions, bundles, product versions, licences.
- REQ-COM-4 (P0) Cart, checkout, order + items, payment attempts, verified payment, receipts, order history.
- REQ-COM-5 (P0) Documented order state machine; no duplicate charge, fulfilment or entitlement.
- REQ-COM-6 (P0) Paystack and Flutterwave adapters: server-side initialise and verify, signed webhooks, idempotency,
  amount/currency verification, retries, reconciliation, refunds, audit log. Client redirects are never trusted.
- REQ-COM-7 (P0) Entitlements drive access to every paid resource (downloads, courses, tickets, premium articles).
- REQ-COM-8 (P0) Secure delivery: private storage, authorisation check, short-lived signed URLs, download limits, access log.
- REQ-COM-9 (P1) Refund workflow (request → approve → provider refund → entitlement revocation → ledger compensation).
- REQ-COM-10 (P1) Sales/revenue reports; administrative reconciliation view.
- No raw card data is ever handled or stored (hosted checkout / provider inline only).

**Acceptance:** Journey A passes end-to-end against provider sandbox.

### 4.4 Learning management (Milestone 4)

- REQ-LMS-1 (P0) Courses with modules and lessons (video, text, code, resources, live link), landing pages,
  pricing via the commerce engine, free preview lessons.
- REQ-LMS-2 (P1) Quizzes (pass mark, attempts), assignments (submission + grading), drip scheduling, bundles, cohorts.
- REQ-LMS-3 (P0) Learner dashboard, lesson player, resume, completion, progress.
- REQ-LMS-4 (P1) Reviews, completion certificates with public verification code/URL.
- REQ-LMS-5 (P0) Protected video via streaming provider: private assets, signed short-lived playback.
  Limitation documented: screen recording cannot be fully prevented.
- REQ-LMS-6 (P1) Course analytics (enrolment, completion, progress, quiz outcomes, revenue, refunds, engagement),
  permission-controlled.

**Acceptance:** Journey B.

### 4.5 Events & coding challenges (Milestone 5)

- REQ-EVT-1 (P0) Event types: seminar, workshop, webinar, talk show, live training, virtual conference,
  competition, hackathon, community challenge.
- REQ-EVT-2 (P0) Landing pages, sessions/schedule with time zones, free/paid ticket types, capacity (race-safe),
  registration, confirmation, reminders, .ics export, virtual links released only to ticket holders.
- REQ-EVT-3 (P1) Waiting lists, cancellation policy, attendance/check-in, certificates, participant & revenue reports.
- REQ-EVT-4 (P1) Coding challenges: statements, categories, rules, eligibility, windows, text/code/file
  submissions, configurable scoring, manual judging with comments, rankings, awards, challenge leaderboards.
- REQ-EVT-5 No code execution on the application server. Automated evaluation (P2) requires a separate isolated runner.

**Acceptance:** Journey E.

### 4.6 Appointments (Milestone 5)

- REQ-BKG-1 (P0) Service types (consultation, mentorship, advisory, training, custom), free/paid.
- REQ-BKG-2 (P0) Availability rules, exceptions/blocked dates, buffers, minimum notice, maximum advance window, time zones.
- REQ-BKG-3 (P0) No double booking (DB constraint + transactional locking).
- REQ-BKG-4 (P0) Confirmation, reminders, cancel/reschedule within policy, history, admin reporting.
- REQ-BKG-5 (P1) External scheduling adapter (Calendly first): connection status, webhooks, sync, cancellations.
  Admin chooses native, external, or both per service. Unconnected integrations are shown as unconnected.

**Acceptance:** Journey C.

### 4.7 Community & moderation (Milestone 6)

- REQ-COMM-1 (P1) Profiles, categories, posts/discussions, Q&A (accepted answers), comments/replies, reactions,
  bookmarks, follows, groups, course- and event-linked discussions, search, notifications.
- REQ-COMM-2 (P1) Safety: rate limits, new-account posting restrictions, link limits, duplicate-content checks,
  reporting, moderator queue, removal, suspension, blocking, audit log of moderation actions.
- REQ-COMM-3 (P1) Visibility: public visitors see only public content; participation depends on account status.

### 4.8 Rewards, achievements, leaderboard (Milestone 6)

- REQ-RWD-1 (P1) "Osiaku Coins" (label editable): closed-loop promotional credit, non-transferable, non-withdrawable.
  Model changes (transfers, cash-out) are explicitly out of scope and would need legal review.
- REQ-RWD-2 (P1) Admin-configured earning rules with limits, eligibility, cooldowns, idempotency per triggering event.
- REQ-RWD-3 (P1) Append-only ledger; corrections only via compensating entries; cash and coins kept separate.
- REQ-RWD-4 (P1) Redemption at checkout: conversion rate, eligible products/categories, min/max, caps, expiry,
  campaigns, budgets; partial coin + cash; concurrency-safe reservation; compensation on failed/cancelled orders.
- REQ-RWD-5 (P1) Reports: issued, redeemed, expired, outstanding liability, suspicious activity.
- REQ-RWD-6 (P1) Achievements/badges (collectible, non-redeemable).
- REQ-RWD-7 (P1) Public leaderboard: **max 20 rows**, weekly/monthly/all-time, visible active period, deterministic
  tie-breakers, badges, profile links only when the member allows it, no balances or contact data exposed.

**Acceptance:** Journey D.

### 4.9 CAPTCHA & form security (Milestone 1 core, Milestone 8 hardening)

- REQ-CAP-1 (P0) Provider abstraction: first-party (default), Google reCAPTCHA, Cloudflare Turnstile.
- REQ-CAP-2 (P0) First-party challenge: server-issued, signed, expiring, single-use, replay-protected,
  rate-limited, accessible (no visual puzzle required), not trivial arithmetic.
- REQ-CAP-3 (P0) Applied to registration, contact, booking requests, newsletter, comments, community posts;
  login after repeated failures.
- REQ-CAP-4 (P0) Provider choice and public keys in admin; secret keys only in server environment.
- REQ-CAP-5 (P0) Tests: valid, invalid, expired, replayed, provider failure, rate limit.

### 4.10 Announcements & notifications (Milestone 2 / 7)

- REQ-ANN-1 (P0) Announcement popup, **global switch OFF by default**; the global switch overrides any schedule/targeting.
- REQ-ANN-2 (P1) Title, message, image, CTA, URL, start/end, audience, frequency, dismissal policy, campaign tag,
  priority; accessible modal (focus trap, Esc, return focus); impression/click/dismiss analytics.
- REQ-NOT-1 (P0) Queued transactional email with templates, retries, delivery logs: verification, password reset,
  order/payment confirmation, enrolment, booking, event reminders, reward transactions, moderation notices.
- REQ-NOT-2 (P1) Per-user notification preferences (transactional/security email cannot be disabled).

### 4.11 Administration & analytics (Milestone 7, built incrementally from Milestone 1)

- REQ-ADM-1 Admin area covering every module listed in Part 17 of the brief, plus audit logs and operational health.
- REQ-ADM-2 Metrics precisely defined (see SYSTEM_ARCHITECTURE §11) to avoid double counting.
- REQ-ADM-3 Financial and personal reports restricted by permission.

## 5. Non-functional requirements

| ID | Requirement |
|---|---|
| NFR-1 Security | OWASP Top 10 / ASVS L2-aligned controls; see SECURITY_THREAT_MODEL.md |
| NFR-2 Accessibility | WCAG 2.2 AA target; reduced motion respected; keyboard operable; visible focus |
| NFR-3 Performance | Public pages: LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms at p75 on mid-range mobile (to be **measured**, not assumed) |
| NFR-4 Availability | Health checks; documented backup (daily) and restore (tested) |
| NFR-5 Privacy | Data minimisation; NDPA (Nigeria Data Protection Act 2023) and GDPR-style rights to be reviewed by a legal advisor |
| NFR-6 Maintainability | Modular monolith, typed code, static analysis, ≥ 80% coverage on financial/access-control services |
| NFR-7 Responsiveness | 360 px phones → 2560 px screens |
| NFR-8 Time | UTC storage; display in user's configured time zone (default Africa/Lagos) |
| NFR-9 Money | Exact arithmetic; currency always stored with amount |
| NFR-10 Honesty | No feature/integration reported as complete without passing acceptance tests |

## 6. Out of scope (unless re-approved)

- Peer-to-peer coin transfers, cash withdrawal of coins.
- Native mobile apps.
- Automated code execution for challenges (until an isolated runner is designed and approved).
- Storing card data / acting as a payment facilitator.
- Multi-tenant (multiple instructors selling independently, payouts).

## 7. Open questions for the product owner

| # | Question | Default if unanswered |
|---|---|---|
| Q1 | Public name form ("Ifeanyi Osiaku" vs "Ifeanyi Marcel Osiaku") | "Ifeanyi Osiaku" |
| Q2 | Provide a clean high-resolution portrait (and optional secondary photos) | **Answered 2026-10-14:** portrait supplied (`apps/web/public/brand/ifeanyi-osiaku-portrait.jpg`, 1054×1492) |
| Q3 | Hosting provider/plan (see DEPLOYMENT_PLAN.md) | **Answered 2026-10-14:** Hostinger. Plan tier still to confirm (VPS KVM 2 recommended) |
| Q4 | Primary currency and whether to sell in USD as well | NGN primary; USD enabled later |
| Q5 | Video provider preference / budget | Bunny Stream (adapter interface allows switching) |
| Q6 | Transactional email provider | Provider-agnostic SMTP/API (e.g. Postmark, Resend, Amazon SES, Brevo) |
| Q7 | Business entity name and address for receipts, terms, privacy policy | Required before taking live payments |
| Q8 | Refund policy terms | Draft policy; owner approval required before launch |
| Q9 | Admin UI approach (see DECISIONS ADR-008) | Filament admin panel |
