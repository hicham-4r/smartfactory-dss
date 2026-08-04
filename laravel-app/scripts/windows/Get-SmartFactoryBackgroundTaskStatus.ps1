param(
    [string] $ProjectPath = (
        Resolve-Path (
            Join-Path $PSScriptRoot '..\..'
        )
    ).Path
)

$ErrorActionPreference = 'Stop'

$ProjectPath = (
    Resolve-Path -LiteralPath $ProjectPath
).Path

$taskPath = '\SmartFactory DSS\'

Write-Host ''
Write-Host 'Scheduled task status' -ForegroundColor Cyan

$tasks = Get-ScheduledTask `
    -TaskPath $taskPath `
    -ErrorAction SilentlyContinue

if ($null -eq $tasks) {
    Write-Host 'No SmartFactory DSS tasks are installed.' `
        -ForegroundColor Yellow

    exit 1
}

$rows = foreach ($task in $tasks) {
    $info = Get-ScheduledTaskInfo `
        -TaskPath $taskPath `
        -TaskName $task.TaskName

    [PSCustomObject]@{
        TaskName = $task.TaskName
        State = $task.State
        LastRunTime = $info.LastRunTime
        LastTaskResult = $info.LastTaskResult
        NextRunTime = $info.NextRunTime
    }
}

$rows |
    Sort-Object TaskName |
    Format-Table -AutoSize

$logDirectory = Join-Path $ProjectPath 'storage\logs'

foreach ($logName in @(
    'windows-scheduler.log',
    'windows-queue-worker.log'
)) {
    $logPath = Join-Path $logDirectory $logName

    Write-Host ''
    Write-Host $logName -ForegroundColor Cyan

    if (Test-Path -LiteralPath $logPath) {
        Get-Content `
            -LiteralPath $logPath `
            -Tail 20
    } else {
        Write-Host 'Log file has not been created yet.'
    }
}
