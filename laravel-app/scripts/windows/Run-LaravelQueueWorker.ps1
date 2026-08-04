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
$logPath = Join-Path $logDirectory 'windows-queue-worker.log'

New-Item `
    -ItemType Directory `
    -Path $logDirectory `
    -Force |
    Out-Null

Set-Location -LiteralPath $ProjectPath

while ($true) {
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    Add-Content `
        -LiteralPath $logPath `
        -Value "[$timestamp] ERP queue worker started."

    try {
        $output = & $PhpPath `
            artisan `
            queue:work `
            database `
            --queue=erp-sync,default `
            --tries=20 `
            --timeout=7200 `
            --sleep=3 `
            --max-time=3600 `
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
    } catch {
        $exitCode = 1

        Add-Content `
            -LiteralPath $logPath `
            -Value (
                "[$timestamp] Queue worker exception: "
                + $_.Exception.Message
            )
    }

    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    Add-Content `
        -LiteralPath $logPath `
        -Value (
            "[$timestamp] ERP queue worker exited with code "
            + "$exitCode. Restarting in 5 seconds."
        )

    Start-Sleep -Seconds 5
}
