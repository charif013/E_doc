param(
    [string] $PhpPath = 'php'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$logPath = Join-Path $projectRoot 'storage\logs\queue-ocr-worker.log'
$mutex = [System.Threading.Mutex]::new($false, 'Local\eDocOcrWorker')

Set-Location -LiteralPath $projectRoot

if (-not $mutex.WaitOne(0)) {
    $mutex.Dispose()
    exit 0
}

try {
    & $PhpPath artisan queue:work database `
        --queue=ocr `
        --sleep=1 `
        --tries=1 `
        --timeout=720 `
        --memory=512 *>> $logPath
} finally {
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}

exit $LASTEXITCODE
