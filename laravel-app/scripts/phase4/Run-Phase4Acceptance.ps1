param(
    [string] $ProjectPath = (
        Resolve-Path (
            Join-Path $PSScriptRoot '..\..'
        )
    ).Path,

    [switch] $SkipFullTests
)

$ErrorActionPreference = 'Stop'

Set-Location -LiteralPath (
    Resolve-Path -LiteralPath $ProjectPath
).Path

function Invoke-Step {
    param(
        [string] $Name,
        [scriptblock] $Command
    )

    Write-Host ""
    Write-Host "=== $Name ===" -ForegroundColor Cyan

    & $Command

    if ($LASTEXITCODE -ne 0) {
        throw "$Name failed with exit code $LASTEXITCODE."
    }
}

Invoke-Step 'Clear caches' {
    php artisan optimize:clear
}

Invoke-Step 'Migration status' {
    php artisan migrate:status
}

Invoke-Step 'ERP handshake' {
    php artisan erp:handshake `
        --resource=products `
        --per-page=1
}

Invoke-Step 'Complete ERP cycle' {
    php artisan erp:sync:cycle `
        --force `
        --per-page=100 `
        --max-pages=100
}

Invoke-Step 'ERP health' {
    php artisan erp:sync:health `
        --details `
        --fail-on-degraded
}

Invoke-Step 'Failed queue jobs' {
    php artisan queue:failed
}

if (-not $SkipFullTests) {
    Invoke-Step 'Complete Laravel test suite' {
        php artisan test --stop-on-failure
    }
}

Write-Host ""
Write-Host 'Phase 4 acceptance completed successfully.' `
    -ForegroundColor Green
