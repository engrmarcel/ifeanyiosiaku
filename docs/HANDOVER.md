# Session Handover

Keep this file current so any session can continue without repeating work.

## Current state (2026-10-15)

- **Milestone 0:** complete. Owner accepted all defaults (Filament admin, VPS hosting target, "Ifeanyi Osiaku").
- **Milestone 1:** core complete and verified (see IMPLEMENTATION_ROADMAP.md §3 for the checklist and test
  results). Partially done: notification templates table (→ M7), Toast/ErrorState
  components (→ M2), Playwright (→ M8). Added: content protection (ADR-024), add-on switches (ADR-025),
  staff 2FA default by environment (ADR-026).
- **Git:** first commit approved by the owner on 2026-10-14; remote `origin` = https://github.com/engrmarcel/ifeanyiosiaku.

## Next actions

1. CI is green on GitHub; the repository is public. Check https://github.com/engrmarcel/ifeanyiosiaku/actions after each push (run list and annotations are readable without login via the GitHub API).
2. Milestone 2 in progress (media library → pages/revisions → block registry → homepage sections).

## Environment notes (this machine)

- Composer: `C:\ProgramData\ComposerSetup\bin\composer` (not on Bash PATH).
- MariaDB: `C:\xampp\mysql\bin\mysql.exe -u root` (10.4.32). **Only touch `ifeanyiosiaku` / `ifeanyiosiaku_testing`**; ~20 other projects' databases share this server.
- Ports: API **8110**, website **3100**. Other projects use 8000, 8090–8092 and 8100 (a Python uvicorn app) — never stop those processes.
- Network: PHP's curl intermittently times out reaching GitHub/Packagist while system `curl` works. Composer
  commands need to run outside the sandbox. If one package keeps failing, pre-fill Composer's cache: download
  the lock file's `dist.url` with system curl to
  `%LOCALAPPDATA%\Composer\files\<vendor>/<package>/<sha1(dist.url)>.zip`, then rerun `composer install`.
- Tests: Laravel JSON test requests only send cookies after `withCredentials()`; `Tests\TestCase::keepSessionFrom()`
  and `solveCaptcha()` handle this.
- Next.js 16: read `apps/web/node_modules/next/dist/docs/` before using unfamiliar APIs (Cache Components is on;
  request-time values such as `searchParams` must sit inside `<Suspense>`).
- Portrait: `apps/web/public/brand/ifeanyi-osiaku-portrait.jpg` (supplied 2026-10-14; used on the interim homepage and as the default social preview). Moves into the media library in Milestone 2.
- Hosting: Hostinger VPS, KVM 2 or larger (decided 2026-10-15). See DEPLOYMENT_PLAN.md §2.1.
- Staff 2FA is NOT required locally (ADR-026); tests force it on via phpunit.xml.
- Visual checks: drive the installed Chrome through the DevTools protocol for real device widths (plain headless Chrome cannot go below 512px).
