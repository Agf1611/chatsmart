$pidFile = Join-Path $PSScriptRoot 'chatsmart-sync-watcher.pid'
$watcherScript = Join-Path $PSScriptRoot 'watch-sync-to-chatsmart.ps1'

if (Test-Path $pidFile) {
    $existingPid = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($existingPid) {
        $process = Get-Process -Id ([int]$existingPid) -ErrorAction SilentlyContinue
        if ($process) {
            Write-Output "Watcher sudah berjalan dengan PID $existingPid"
            exit 0
        }
    }
}

$process = Start-Process -FilePath 'powershell' -ArgumentList @(
    '-ExecutionPolicy',
    'Bypass',
    '-File',
    $watcherScript
) -WindowStyle Hidden -PassThru

Set-Content -Path $pidFile -Value $process.Id
Write-Output "Watcher berhasil dijalankan. PID=$($process.Id)"
