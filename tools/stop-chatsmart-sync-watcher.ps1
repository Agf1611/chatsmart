$pidFile = Join-Path $PSScriptRoot 'chatsmart-sync-watcher.pid'

if (-not (Test-Path $pidFile)) {
    Write-Output 'Watcher belum berjalan.'
    exit 0
}

$pidValue = Get-Content $pidFile -ErrorAction SilentlyContinue
if ($pidValue) {
    $process = Get-Process -Id ([int]$pidValue) -ErrorAction SilentlyContinue
    if ($process) {
        Stop-Process -Id $process.Id -Force
        Write-Output "Watcher dihentikan. PID=$pidValue"
    } else {
        Write-Output 'PID watcher tidak aktif lagi.'
    }
}

Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
