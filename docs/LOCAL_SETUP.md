# Local Setup (Windows + XAMPP)

This guide gets the platform running on this computer. Commands are typed into **PowerShell**, opened in the
project folder `C:\xampp\htdocs\ifeanyiosiaku`.

## What you need (already installed on this machine as of 2026-10-14)

| Tool | Version found | Notes |
|---|---|---|
| PHP (XAMPP) | 8.3.33 | `C:\xampp\php\php.exe` |
| Composer | 2.10.3 | `C:\ProgramData\ComposerSetup\bin\composer.bat` |
| MariaDB (XAMPP) | 10.4.32 | Must be running (XAMPP Control Panel → MySQL → Start). Upgrading to MySQL 8.4 is recommended (ADR-014). |
| Node.js / npm | 24.20 / 11.19 | Node 22.12 or newer works |

## 1. First-time setup

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
```

This is safe to run more than once. It:

1. Creates the `ifeanyiosiaku` and `ifeanyiosiaku_testing` databases **if they do not already exist** (no other database is touched).
2. Installs the API and website dependencies.
3. Creates `apps\api\.env` and `apps\web\.env.local` from the examples if missing.
4. Applies database migrations and seed data: roles and permissions, a local demo member, and the starting content (your profile, portrait, skills and a published homepage).

> **Network note.** On this machine, PHP sometimes cannot reach GitHub/Packagist even when the browser can. If
> `composer install` fails with "curl error 28" or "Permission denied … tmp-….zip", simply run it again; see
> *Troubleshooting* below.

## 2. Create your owner (Super Administrator) account

```powershell
cd apps\api
C:\xampp\php\php.exe artisan app:create-super-admin
```

You will be asked for your email, name and password (typed privately, never stored in history).

## 3. Start everything

```powershell
powershell -ExecutionPolicy Bypass -File scripts\dev.ps1
```

Four windows open: the API (port **8110**), the queue worker (sends emails), the scheduler, and the website
(port **3100**). Close those windows to stop.

Open **http://localhost:3100**.

- Sign in with your owner account.
- Go to **Account → Security** and turn on **two-factor authentication** (required for the admin panel).
- Then open **http://localhost:3100/admin**.

> Ports 8000 and 8090–8100 are used by other projects on this computer, which is why this project uses 8110 and 3100.

## 4. Publishing and instant updates

When a page is published, Laravel asks the website to refresh it. This needs the **queue window** running and the
same `WEB_REVALIDATE_SECRET` in `appsapi.env` and `appsweb.env.local`. Without it, changes still appear within an hour.

## 5. Emails during development

`MAIL_MAILER=log`: no real email is sent. Verification and password-reset messages are written to
`apps\api\storage\logs\laravel-YYYY-MM-DD.log`. Open the newest log file and search for "Confirm email address"
or "Choose a new password" to find the link. The queue worker window must be running for emails to be produced.

## 6. Demo account (local only)

| Email | Password | Role |
|---|---|---|
| `member@example.test` | `local-dev-password` | Member |

Created by `DevelopmentSeeder`, which refuses to run outside `local`/`testing`.

## 7. Run all automated checks

```powershell
powershell -ExecutionPolicy Bypass -File scripts\test.ps1
```

Runs code style, static analysis and tests for both apps. Tests use the separate `ifeanyiosiaku_testing`
database, which they reset; your development data is untouched.

## How the pieces connect

```
Browser ──> http://localhost:3100  (Next.js website)
              ├── /api/*, /admin, admin assets ──proxy──> http://127.0.0.1:8110 (Laravel)
              └── everything else rendered by Next.js
```

Because the browser only ever talks to `localhost:3100`, the login cookie is shared by the website and the admin
panel, and no cross-site (CORS) configuration is needed. `APP_URL` in `apps\api\.env` is therefore
`http://localhost:3100`, the public address.

**The project is deliberately not served by XAMPP's Apache.** A deny-all `.htaccess` at the project root stops
`http://localhost/ifeanyiosiaku/...` from exposing configuration files.

## Troubleshooting

| Problem | Fix |
|---|---|
| `composer install` fails with curl error 28 / "Permission denied … tmp-….zip" | PHP timed out reaching GitHub. Run the command again. If it keeps failing on the same package, download it with `curl` into Composer's cache (the cache key is `sha1(dist URL)`); see `docs/HANDOVER.md`. |
| "Port 8110/3100 is already in use" | Another program is using it. Close it, or change the port in `apps\web\.env.local` (`API_INTERNAL_URL`), `apps\web\package.json` and `scripts\dev.ps1`. |
| Website shows "Something went wrong" on sign-in | The API window is not running, or MySQL is stopped in XAMPP. |
| No verification email in the log | The queue window is not running. |
| Changed `API_INTERNAL_URL` but the proxy still uses the old value | Restart `npm run dev` (or rebuild for `npm run start`). Rewrites are read when the website starts/builds. |
