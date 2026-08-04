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

$phpCommand = Get-Command php -ErrorAction Stop
$PhpPath = (& $phpCommand.Source -r 'echo PHP_BINARY;').Trim()

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "PHP executable not found: $PhpPath"
}

$artisanPath = Join-Path $ProjectPath 'artisan'

if (-not (Test-Path -LiteralPath $artisanPath -PathType Leaf)) {
    throw "Laravel artisan file not found: $artisanPath"
}

$schedulerScript = Join-Path `
    $ProjectPath `
    'scripts\windows\Invoke-LaravelScheduler.ps1'

$workerScript = Join-Path `
    $ProjectPath `
    'scripts\windows\Run-LaravelQueueWorker.ps1'

foreach ($script in @($schedulerScript, $workerScript)) {
    if (-not (Test-Path -LiteralPath $script -PathType Leaf)) {
        throw "Required script not found: $script"
    }
}

$taskPath = '\SmartFactory DSS\'
$schedulerTaskName = 'Laravel Scheduler'
$workerTaskName = 'ERP Queue Worker'

$currentUser = (
    [System.Security.Principal.WindowsIdentity]::GetCurrent()
).Name

$powerShellPath = (
    Get-Command powershell.exe -ErrorAction Stop
).Source

function New-HiddenPowerShellAction {
    param(
        [Parameter(Mandatory = $true)]
        [string] $ScriptPath
    )

    $arguments = @(
        '-NoLogo'
        '-NoProfile'
        '-NonInteractive'
        '-ExecutionPolicy Bypass'
        '-WindowStyle Hidden'
        '-File'
        ('"{0}"' -f $ScriptPath)
        '-ProjectPath'
        ('"{0}"' -f $ProjectPath)
        '-PhpPath'
        ('"{0}"' -f $PhpPath)
    ) -join ' '

    return New-ScheduledTaskAction `
        -Execute $powerShellPath `
        -Argument $arguments `
        -WorkingDirectory $ProjectPath
}

$principal = New-ScheduledTaskPrincipal `
    -UserId $currentUser `
    -LogonType Interactive `
    -RunLevel Limited

$schedulerAction = New-HiddenPowerShellAction `
    -ScriptPath $schedulerScript

$schedulerTrigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 3650)

$schedulerSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask `
    -TaskPath $taskPath `
    -TaskName $schedulerTaskName `
    -Action $schedulerAction `
    -Trigger $schedulerTrigger `
    -Principal $principal `
    -Settings $schedulerSettings `
    -Description (
        'Runs Laravel schedule:run every minute for SmartFactory DSS.'
    ) `
    -Force |
    Out-Null

$workerAction = New-HiddenPowerShellAction `
    -ScriptPath $workerScript

$workerTrigger = New-ScheduledTaskTrigger `
    -AtLogOn `
    -User $currentUser

$workerSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero)

Register-ScheduledTask `
    -TaskPath $taskPath `
    -TaskName $workerTaskName `
    -Action $workerAction `
    -Trigger $workerTrigger `
    -Principal $principal `
    -Settings $workerSettings `
    -Description (
        'Runs the SmartFactory DSS ERP and default database queue worker.'
    ) `
    -Force |
    Out-Null

Start-ScheduledTask `
    -TaskPath $taskPath `
    -TaskName $schedulerTaskName

Start-ScheduledTask `
    -TaskPath $taskPath `
    -TaskName $workerTaskName

Write-Host ''
Write-Host 'SmartFactory background tasks installed.' `
    -ForegroundColor Green

Write-Host "Project: $ProjectPath"
Write-Host "PHP:     $PhpPath"
Write-Host "User:    $currentUser"
Write-Host ''

Get-ScheduledTask `
    -TaskPath $taskPath |
    Sort-Object TaskName |
    Select-Object TaskName, State |
    Format-Table -AutoSize
