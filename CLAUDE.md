# Project guide for coding agents

Read `docs/HANDOVER.md` first, then the milestone section of `docs/IMPLEMENTATION_ROADMAP.md`.

- Monorepo: `apps/api` (Laravel 13, Filament 5, Fortify), `apps/web` (Next.js 16 — read
  `apps/web/node_modules/next/dist/docs/` before using unfamiliar Next APIs; see `apps/web/AGENTS.md`).
- Domain code lives in `apps/api/app/Domain/<Module>` (Models, Actions, Http, Enums, ...). `User` is the exception
  (`App\Models\User`, ADR-021).
- Same-origin architecture: the website proxies `/api`, `/admin` and admin assets to Laravel (ADR-003). `APP_URL`
  is the public origin.
- Run all checks: `powershell -ExecutionPolicy Bypass -File scripts\test.ps1` (backend tests use the
  `ifeanyiosiaku_testing` database).
- Never touch other databases on the local MariaDB server or processes on ports 8000/8090–8092/8100.
- Record significant decisions in `docs/DECISIONS.md`; keep `docs/INTEGRATION_REGISTER.md` statuses honest.
- The owner is not a developer: explain decisions in plain language and never report untested work as done.
