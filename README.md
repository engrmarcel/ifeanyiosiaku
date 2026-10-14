# Ifeanyi Osiaku — IO Digital Ecosystem

Personal brand, portfolio, learning, digital commerce and community platform for Ifeanyi Osiaku.

**Status:** Milestone 1 (foundation) in progress — authentication, roles, CAPTCHA, admin panel shell and design
system are implemented and tested. No content, commerce or learning features yet. Not production-ready.

| Part | Technology | Folder |
|---|---|---|
| Website, member and learner areas | Next.js 16, React 19, TypeScript, Tailwind CSS 4 | [apps/web](apps/web) |
| API, admin panel, background jobs | Laravel 13, PHP 8.3, Filament 5, Fortify | [apps/api](apps/api) |
| Database | MySQL 8.4 (MariaDB 10.4 locally) | migrations in `apps/api/database/migrations` |

## Quick start (Windows + XAMPP)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1   # once
powershell -ExecutionPolicy Bypass -File scripts\dev.ps1     # every time
```

Open http://localhost:3100. Full instructions: [docs/LOCAL_SETUP.md](docs/LOCAL_SETUP.md).

Run all checks: `powershell -ExecutionPolicy Bypass -File scripts\test.ps1`

## Documentation

| Document | Purpose |
|---|---|
| [PRODUCT_REQUIREMENTS](docs/PRODUCT_REQUIREMENTS.md) | What the platform must do |
| [SYSTEM_ARCHITECTURE](docs/SYSTEM_ARCHITECTURE.md) | How it is built |
| [DATABASE_DESIGN](docs/DATABASE_DESIGN.md) | Data model |
| [SECURITY_THREAT_MODEL](docs/SECURITY_THREAT_MODEL.md) | Threats and controls |
| [IMPLEMENTATION_ROADMAP](docs/IMPLEMENTATION_ROADMAP.md) | Milestones, progress, risks, definition of done |
| [INTEGRATION_REGISTER](docs/INTEGRATION_REGISTER.md) | External services and their real status |
| [DEPLOYMENT_PLAN](docs/DEPLOYMENT_PLAN.md) | Hosting requirements and plan |
| [DECISIONS](docs/DECISIONS.md) | Architecture decision log |
| [LOCAL_SETUP](docs/LOCAL_SETUP.md) | Running it on this computer |
| [HANDOVER](docs/HANDOVER.md) | Current state and next steps |
