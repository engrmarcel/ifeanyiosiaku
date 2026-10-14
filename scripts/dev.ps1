# Starts the API (port 8110), a queue worker (sends emails), the scheduler, and the website (port 3100)
# in separate windows. Close the windows to stop them.
#
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\dev.ps1
# Then open http://localhost:3100

$root = Split-Path -Parent $PSScriptRoot
$api = Join-Path $root 'apps\api'
$web = Join-Path $root 'apps\web'
$php = 'C:\xampp\php\php.exe'

foreach ($port in 8110, 3100) {
    if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
        Write-Host "Port $port is already in use. Stop the program using it first." -ForegroundColor Red
        exit 1
    }
}

Start-Process powershell -WorkingDirectory $api -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='IO API :8110'; & '$php' artisan serve --host=127.0.0.1 --port=8110"
Start-Process powershell -WorkingDirectory $api -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='IO queue'; & '$php' artisan queue:work --tries=3"
Start-Process powershell -WorkingDirectory $api -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='IO scheduler'; & '$php' artisan schedule:work"
Start-Process powershell -WorkingDirectory $web -ArgumentList '-NoExit', '-Command', "`$host.UI.RawUI.WindowTitle='IO website :3100'; npm run dev"

Write-Host 'Started. Open http://localhost:3100  (admin panel: http://localhost:3100/admin)' -ForegroundColor Green
Write-Host 'Emails are written to apps\api\storage\logs\laravel-*.log while MAIL_MAILER=log.'
