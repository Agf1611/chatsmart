param(
    [string]$SourceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$TargetRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\chatsmart')).Path
)

$ErrorActionPreference = 'Stop'

$syncScript = Join-Path $PSScriptRoot 'sync-to-chatsmart.ps1'
$logFile = Join-Path $SourceRoot 'storage\logs\chatsmart-sync-watcher.log'

$ignorePrefixes = @(
    [System.IO.Path]::GetFullPath($TargetRoot),
    [System.IO.Path]::GetFullPath((Join-Path $SourceRoot 'node_modules')),
    [System.IO.Path]::GetFullPath((Join-Path $SourceRoot 'vendor')),
    [System.IO.Path]::GetFullPath((Join-Path $SourceRoot 'credentials')),
    [System.IO.Path]::GetFullPath((Join-Path $SourceRoot 'dist')),
    [System.IO.Path]::GetFullPath((Join-Path $SourceRoot 'tmp'))
)

function Write-WatcherLog {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -Path $logFile -Value $line
}

function Is-IgnoredPath {
    param([string]$Path)
    if (-not $Path) {
        return $true
    }

    $fullPath = [System.IO.Path]::GetFullPath($Path)

    if ([System.IO.Path]::GetFileName($fullPath) -eq '.env') {
        return $true
    }

    foreach ($prefix in $ignorePrefixes) {
        if ($fullPath.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    return $false
}

function Invoke-Sync {
    try {
        & powershell -ExecutionPolicy Bypass -File $syncScript | Out-Null
        Write-WatcherLog 'Sinkronisasi otomatis berhasil dijalankan.'
    } catch {
        Write-WatcherLog ("Sinkronisasi otomatis gagal: " + $_.Exception.Message)
    }
}

Write-WatcherLog 'Watcher dimulai.'
Invoke-Sync

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $SourceRoot
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

$script:lastSync = Get-Date '2000-01-01'

$action = {
    $path = $Event.SourceEventArgs.FullPath
    if (Is-IgnoredPath $path) {
        return
    }

    $now = Get-Date
    if (($now - $script:lastSync).TotalSeconds -lt 2) {
        return
    }

    $script:lastSync = $now
    Write-WatcherLog ("Perubahan terdeteksi: " + $path)
    Invoke-Sync
}

Register-ObjectEvent $watcher Created -SourceIdentifier 'ChatSmartSyncCreated' -Action $action | Out-Null
Register-ObjectEvent $watcher Changed -SourceIdentifier 'ChatSmartSyncChanged' -Action $action | Out-Null
Register-ObjectEvent $watcher Deleted -SourceIdentifier 'ChatSmartSyncDeleted' -Action $action | Out-Null
Register-ObjectEvent $watcher Renamed -SourceIdentifier 'ChatSmartSyncRenamed' -Action $action | Out-Null

while ($true) {
    Wait-Event -Timeout 5 | Out-Null
}
