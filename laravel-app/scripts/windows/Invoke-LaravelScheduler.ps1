param(
    [Parameter(Mandatory = $true)]
    [string] $ProjectPath,

    [Parameter(Mandatory = $true)]
    [string] $PhpPath
)

$ErrorActionPreference = 'Stop'

$ProjectPath = (
    Resolve-Path -LiteralPath $ProjectPath
).Path

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "PHP executable not found: $PhpPath"
}

$artisanPath = Join-Path $ProjectPath 'artisan'

if (-not (Test-Path -LiteralPath $artisanPath -PathType Leaf)) {
    throw "Laravel artisan file not found: $artisanPath"
}

$logDirectory = Join-Path $ProjectPath 'storage\logs'
$logPath = Join-Path $logDirectory 'windows-scheduler.log'

New-Item `
    -ItemType Directory `
    -Path $logDirectory `
    -Force |
    Out-Null

Set-Location -LiteralPath $ProjectPath

$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

Add-Content `
    -LiteralPath $logPath `
    -Value "[$timestamp] schedule:run started."

$output = & $PhpPath `
    artisan `
    schedule:run `
    --no-interaction `
    2>&1

$exitCode = $LASTEXITCODE

if ($null -ne $output) {
    $output |
        Out-File `
            -LiteralPath $logPath `
            -Append `
            -Encoding utf8
}

$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

Add-Content `
    -LiteralPath $logPath `
    -Value "[$timestamp] schedule:run finished with exit code $exitCode."

exit $exitCode
