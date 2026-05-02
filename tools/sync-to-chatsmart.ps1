param(
    [string]$SourceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$TargetRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\chatsmart')).Path,
    [switch]$Mirror
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $SourceRoot)) {
    throw "Source root tidak ditemukan: $SourceRoot"
}

if (-not (Test-Path $TargetRoot)) {
    throw "Target chatsmart tidak ditemukan: $TargetRoot"
}

$sourceFull = [System.IO.Path]::GetFullPath($SourceRoot)
$targetFull = [System.IO.Path]::GetFullPath($TargetRoot)

if ($sourceFull -eq $targetFull) {
    throw 'Source dan target tidak boleh sama.'
}

$excludeDirs = @(
    (Join-Path $sourceFull 'chatsmart'),
    (Join-Path $sourceFull '.git'),
    (Join-Path $sourceFull 'node_modules'),
    (Join-Path $sourceFull 'vendor'),
    (Join-Path $sourceFull 'credentials'),
    (Join-Path $sourceFull 'dist'),
    (Join-Path $sourceFull 'tmp'),
    (Join-Path $sourceFull 'storage\logs'),
    (Join-Path $sourceFull 'storage\framework\cache'),
    (Join-Path $sourceFull 'storage\framework\sessions'),
    (Join-Path $sourceFull 'storage\framework\testing'),
    (Join-Path $sourceFull 'storage\framework\views'),
    (Join-Path $sourceFull 'storage\app\backups')
)

$excludeFiles = @(
    '.env',
    '.env.backup',
    '.DS_Store'
)

$roboArgs = @(
    $sourceFull,
    $targetFull,
    '/E',
    '/R:1',
    '/W:1',
    '/NFL',
    '/NDL',
    '/NP',
    '/NJH',
    '/NJS'
)

if ($Mirror) {
    $roboArgs += '/MIR'
}

if ($excludeDirs.Count -gt 0) {
    $roboArgs += '/XD'
    $roboArgs += $excludeDirs
}

if ($excludeFiles.Count -gt 0) {
    $roboArgs += '/XF'
    $roboArgs += $excludeFiles
}

Write-Host "Sinkronisasi source ke chatsmart..."
Write-Host "Source : $sourceFull"
Write-Host "Target : $targetFull"

& robocopy @roboArgs | Out-Host

$exitCode = $LASTEXITCODE
if ($exitCode -ge 8) {
    throw "Robocopy gagal dengan exit code $exitCode"
}

Write-Host "Selesai. Exit code robocopy: $exitCode"
