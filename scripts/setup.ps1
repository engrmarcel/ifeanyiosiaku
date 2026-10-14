# One-time (and safe to repeat) local setup for Windows + XAMPP.
# It never deletes data: migrations only apply changes that have not run yet.
#
# Usage (from the project folder):   powershell -ExecutionPolicy Bypass -File scripts\setup.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$api = Join-Path $root 'apps\api'
$web = Join-Path $root 'apps\web'
$php = 'C:\xampp\php\php.exe'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$composer = 'C:\ProgramData\ComposerSetup\bin\composer.bat'

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Green }

foreach ($tool in @($php, $mysql, $composer)) {
    if (-not (Test-Path $tool)) { throw "Required tool not found: $tool" }
}
if (-not (Get-Command node -ErrorAction SilentlyContinue)) { throw 'Node.js 22 or newer is required.' }

Step 'Creating databases (only if they do not exist)'
& $mysql -u root -e "CREATE DATABASE IF NOT EXISTS ifeanyiosiaku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS ifeanyiosiaku_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

Step 'Installing API dependencies'
Push-Location $api
& $composer install --no-interaction --no-progress
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    & $php artisan key:generate
    Write-Host 'Created apps\api\.env' -ForegroundColor Yellow
}

Step 'Applying database migrations and required seed data'
& $php artisan migrate --force
& $php artisan db:seed --force
Pop-Location

Step 'Installing website dependencies'
Push-Location $web
npm ci
if (-not (Test-Path '.env.local')) {
    Copy-Item '.env.example' '.env.local'
    Write-Host 'Created apps\web\.env.local' -ForegroundColor Yellow
}
Pop-Location

Step 'Done'
Write-Host 'Next: create your owner account with'
Write-Host '   cd apps\api; C:\xampp\php\php.exe artisan app:create-super-admin' -ForegroundColor Cyan
Write-Host 'Then start everything with:  powershell -ExecutionPolicy Bypass -File scripts\dev.ps1' -ForegroundColor Cyan
