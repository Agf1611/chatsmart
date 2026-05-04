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
    (Join-Path $sourceFull 'storage\app\backups'),
    (Join-Path $sourceFull 'storage\app\file-manager'),
    (Join-Path $sourceFull 'storage\app\files'),
    (Join-Path $sourceFull 'storage\app\public'),
    (Join-Path $sourceFull 'storage\app\temp'),
    (Join-Path $sourceFull 'storage\app\wachecker'),
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
    '.DS_Store',
    'sickas_rules_snapshot.txt',
    '*.zip',
    'wagethosting.zip'
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

function Remove-TargetPathIfExists {
    param(
        [string]$Path
    )

    if (Test-Path -LiteralPath $Path) {
        Remove-Item -LiteralPath $Path -Recurse -Force
    }
}

function Clear-DirectoryContentsExceptGitignore {
    param(
        [string]$Path
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    Get-ChildItem -LiteralPath $Path -Force | Where-Object { $_.Name -ne '.gitignore' } | ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Recurse -Force
    }
}

function Ensure-GitignorePlaceholder {
    param(
        [string]$DirectoryPath
    )

    if (-not (Test-Path -LiteralPath $DirectoryPath)) {
        New-Item -ItemType Directory -Path $DirectoryPath -Force | Out-Null
    }

    $gitignorePath = Join-Path $DirectoryPath '.gitignore'
    if (-not (Test-Path -LiteralPath $gitignorePath)) {
        Set-Content -LiteralPath $gitignorePath -Value @(
            '*'
            '!.gitignore'
        )
    }
}

Write-Host "Sinkronisasi source ke chatsmart..."
Write-Host "Source : $sourceFull"
Write-Host "Target : $targetFull"

& robocopy @roboArgs | Out-Host

$exitCode = $LASTEXITCODE
if ($exitCode -ge 8) {
    throw "Robocopy gagal dengan exit code $exitCode"
}

$targetCleanupDirs = @(
    (Join-Path $targetFull 'vendor'),
    (Join-Path $targetFull 'node_modules'),
    (Join-Path $targetFull 'dist'),
    (Join-Path $targetFull 'tmp')
)

foreach ($cleanupDir in $targetCleanupDirs) {
    Remove-TargetPathIfExists -Path $cleanupDir
}

Get-ChildItem -LiteralPath $targetFull -Recurse -Force -File -Filter '*.zip' | ForEach-Object {
    Remove-Item -LiteralPath $_.FullName -Force
}

$targetRuntimeDirs = @(
    (Join-Path $targetFull 'credentials'),
    (Join-Path $targetFull 'storage\logs'),
    (Join-Path $targetFull 'storage\framework\cache\data'),
    (Join-Path $targetFull 'storage\framework\sessions'),
    (Join-Path $targetFull 'storage\framework\testing'),
    (Join-Path $targetFull 'storage\framework\views'),
    (Join-Path $targetFull 'storage\app\backups'),
    (Join-Path $targetFull 'storage\app\file-manager'),
    (Join-Path $targetFull 'storage\app\files'),
    (Join-Path $targetFull 'storage\app\public'),
    (Join-Path $targetFull 'storage\app\temp'),
    (Join-Path $targetFull 'storage\app\wachecker')
)

foreach ($runtimeDir in $targetRuntimeDirs) {
    Clear-DirectoryContentsExceptGitignore -Path $runtimeDir
}

Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'credentials')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\logs')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\framework\cache\data')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\framework\sessions')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\framework\testing')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\framework\views')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\app\backups')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\app\file-manager')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\app\files')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\app\public')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\app\temp')
Ensure-GitignorePlaceholder -DirectoryPath (Join-Path $targetFull 'storage\app\wachecker')

Get-ChildItem -LiteralPath $targetFull -Recurse -Force -File -Filter '.DS_Store' | ForEach-Object {
    Remove-Item -LiteralPath $_.FullName -Force
}

$targetGitPath = Join-Path $targetFull '.git'
if (Test-Path -LiteralPath $targetGitPath) {
    git -C $targetFull clean -fdX | Out-Host
}

Write-Host "Selesai. Exit code robocopy: $exitCode"
