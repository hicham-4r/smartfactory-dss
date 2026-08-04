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

    Unregister-ScheduledTask `
        -TaskPath $taskPath `
        -TaskName $taskName `
        -Confirm:$false `
        -ErrorAction SilentlyContinue
}

Write-Host 'SmartFactory DSS background tasks removed.' `
    -ForegroundColor Green
