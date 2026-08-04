$ErrorActionPreference = 'Stop'

$taskPath = '\SmartFactory DSS\'
$taskNames = @(
    'Laravel Scheduler',
    'ERP Queue Worker'
)

foreach ($taskName in $taskNames) {
    Stop-ScheduledTask `
        -TaskPath $taskPath `
        -TaskName $taskName `
        -ErrorAction SilentlyContinue
}

Start-Sleep -Seconds 2

foreach ($taskName in $taskNames) {
    Start-ScheduledTask `
        -TaskPath $taskPath `
        -TaskName $taskName
}

Write-Host 'SmartFactory DSS background tasks restarted.' `
    -ForegroundColor Green
