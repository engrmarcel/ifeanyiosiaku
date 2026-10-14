# Runs every automated check (the same ones CI runs). Uses the separate ifeanyiosiaku_testing database,
# which the tests reset; your development data is not touched.
#
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\test.ps1

$root = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$failed = @()

function Check($name, [scriptblock]$command) {
    Write-Host "`n==> $name" -ForegroundColor Green
    & $command
    if ($LASTEXITCODE -ne 0) { $script:failed += $name }
}

Push-Location (Join-Path $root 'apps\api')
& $php artisan config:clear | Out-Null
Check 'API code style' { & $php vendor/bin/pint --test }
Check 'API static analysis' { & $php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress }
Check 'API tests' { & $php vendor/bin/pest }
Pop-Location

Push-Location (Join-Path $root 'apps\web')
Check 'Website lint' { npm run lint }
Check 'Website type check' { npm run typecheck }
Check 'Website tests' { npm test }
Pop-Location

if ($failed.Count -gt 0) {
    Write-Host "`nFAILED: $($failed -join ', ')" -ForegroundColor Red
    exit 1
}
Write-Host "`nAll checks passed." -ForegroundColor Green
