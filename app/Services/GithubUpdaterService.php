<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class GithubUpdaterService
{
    public function getStatus()
    {
        $status = [
            'configured' => (bool) config('services.github_updater.repo_url'),
            'branch' => config('services.github_updater.branch', 'main'),
            'mirror_path' => $this->getMirrorPath(),
            'mirror_enabled' => $this->isMirrorEnabled(),
            'exclude_paths' => $this->getExcludedPaths(),
            'last_sync' => $this->readLastSync(),
            'remote' => null,
            'remote_error' => null,
            'pending_changes' => [],
            'pending_summary' => [
                'total' => 0,
                'added' => 0,
                'modified' => 0,
                'removed' => 0,
                'renamed' => 0,
            ],
            'is_up_to_date' => false,
        ];

        if (!$status['configured']) {
            return $status;
        }

        try {
            $status['remote'] = $this->fetchRemoteStatus();
            $pending = $this->resolvePendingChanges($status['remote'], $status['last_sync']);
            $status['pending_changes'] = $pending['files'];
            $status['pending_summary'] = $pending['summary'];
            $status['is_up_to_date'] = $pending['is_up_to_date'];
        } catch (Throwable $e) {
            $status['remote_error'] = $e->getMessage();
        }

        return $status;
    }

    public function sync()
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZIP pada PHP tidak tersedia.');
        }

        $remote = $this->fetchRemoteStatus();
        $workingDirectory = $this->prepareWorkingDirectory();
        $backupDirectory = $this->prepareBackupDirectory();

        try {
            $archivePath = $this->downloadArchive($remote, $workingDirectory);
            $sourceRoot = $this->extractArchive($archivePath, $workingDirectory);
            $result = $this->applyUpdates($sourceRoot, $backupDirectory);

            $summary = array_merge($result, [
                'repository' => $remote['repository'],
                'branch' => $remote['branch'],
                'mirror_path' => $this->getMirrorPath(),
                'latest_commit' => $remote['latest_commit'],
                'latest_commit_short' => $remote['latest_commit_short'],
                'latest_message' => $remote['latest_message'],
                'latest_date' => $remote['latest_date'],
                'backup_directory' => $backupDirectory,
                'synced_at' => now()->toDateTimeString(),
            ]);

            $this->writeLastSync($summary);

            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:clear');

            return $summary;
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    protected function fetchRemoteStatus()
    {
        $repository = $this->parseRepository(config('services.github_updater.repo_url'));
        $repoData = $this->githubJson(sprintf('repos/%s/%s', $repository['owner'], $repository['repo']));

        $branch = (string) config('services.github_updater.branch', '');
        if ($branch === '') {
            $branch = data_get($repoData, 'default_branch', 'main');
        }

        $commitData = $this->githubJson(sprintf(
            'repos/%s/%s/commits/%s',
            $repository['owner'],
            $repository['repo'],
            rawurlencode($branch)
        ));

        $sha = (string) data_get($commitData, 'sha', '');
        $message = trim((string) data_get($commitData, 'commit.message', ''));

        return [
            'repository' => $repository['owner'] . '/' . $repository['repo'],
            'owner' => $repository['owner'],
            'repo' => $repository['repo'],
            'branch' => $branch,
            'is_private' => (bool) data_get($repoData, 'private', false),
            'latest_commit' => $sha,
            'latest_commit_short' => $sha !== '' ? substr($sha, 0, 7) : '-',
            'latest_message' => Str::before($message, "\n"),
            'latest_date' => data_get($commitData, 'commit.committer.date'),
            'latest_files' => $this->normalizeFiles(data_get($commitData, 'files', [])),
            'archive_url' => sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s',
                $repository['owner'],
                $repository['repo'],
                rawurlencode($branch)
            ),
        ];
    }

    protected function githubJson($path)
    {
        $response = $this->githubRequest()->get('https://api.github.com/' . ltrim($path, '/'));

        if (!$response->successful()) {
            throw new RuntimeException($this->formatGithubError($response->status(), data_get($response->json(), 'message')));
        }

        return $response->json();
    }

    protected function downloadArchive(array $remote, $workingDirectory)
    {
        $archivePath = $workingDirectory . DIRECTORY_SEPARATOR . 'update.zip';
        $response = $this->githubRequest()
            ->withOptions(['sink' => $archivePath])
            ->get($remote['archive_url']);

        if (!$response->successful()) {
            throw new RuntimeException($this->formatGithubError($response->status(), data_get($response->json(), 'message')));
        }

        if (!File::exists($archivePath) || File::size($archivePath) <= 0) {
            throw new RuntimeException('Arsip update GitHub gagal diunduh.');
        }

        return $archivePath;
    }

    protected function extractArchive($archivePath, $workingDirectory)
    {
        $extractPath = $workingDirectory . DIRECTORY_SEPARATOR . 'source';
        $this->ensureDirectory($extractPath);

        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Arsip update GitHub tidak bisa dibuka.');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $directories = array_values(File::directories($extractPath));
        if (empty($directories)) {
            throw new RuntimeException('Folder hasil ekstraksi update tidak ditemukan.');
        }

        return $directories[0];
    }

    protected function applyUpdates($sourceRoot, $backupDirectory)
    {
        $result = [
            'updated_files' => 0,
            'new_files' => 0,
            'unchanged_files' => 0,
            'skipped_files' => 0,
            'backed_up_files' => 0,
            'mirrored_files' => 0,
            'sample_changed_files' => [],
        ];

        $mirrorRoot = $this->getMirrorPath();
        $mirrorEnabled = $this->isMirrorEnabled();

        foreach ($this->listSourceFiles($sourceRoot) as $sourcePath) {
            $relativePath = $this->relativePath($sourceRoot, $sourcePath);

            if ($this->shouldExclude($relativePath)) {
                $result['skipped_files']++;
                continue;
            }

            $targetPath = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

            if (File::exists($targetPath) && sha1_file($sourcePath) === sha1_file($targetPath)) {
                $result['unchanged_files']++;
                continue;
            }

            $this->ensureDirectory(dirname($targetPath));

            if (File::exists($targetPath)) {
                $backupTarget = $backupDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $this->ensureDirectory(dirname($backupTarget));
                File::copy($targetPath, $backupTarget);
                $result['backed_up_files']++;
                $result['updated_files']++;
            } else {
                $result['new_files']++;
            }

            File::copy($sourcePath, $targetPath);

            if ($mirrorEnabled) {
                $mirrorTargetPath = $mirrorRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $this->ensureDirectory(dirname($mirrorTargetPath));
                File::copy($sourcePath, $mirrorTargetPath);
                $result['mirrored_files']++;
            }

            if (count($result['sample_changed_files']) < 15) {
                $result['sample_changed_files'][] = $relativePath;
            }
        }

        return $result;
    }

    protected function listSourceFiles($sourceRoot)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    protected function relativePath($root, $path)
    {
        $normalizedRoot = str_replace('\\', '/', rtrim($root, '\\/'));
        $normalizedPath = str_replace('\\', '/', $path);

        return ltrim(Str::after($normalizedPath, $normalizedRoot), '/');
    }

    protected function shouldExclude($relativePath)
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        foreach ($this->getExcludedPaths() as $excludedPath) {
            $excludedPath = trim(str_replace('\\', '/', $excludedPath));
            if ($excludedPath === '') {
                continue;
            }

            if (substr($excludedPath, -1) === '/') {
                if (Str::startsWith($relativePath, $excludedPath)) {
                    return true;
                }

                continue;
            }

            if ($relativePath === $excludedPath) {
                return true;
            }
        }

        return false;
    }

    protected function getExcludedPaths()
    {
        $configured = config('services.github_updater.exclude_paths', []);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $defaults = [
            '.env',
            'storage/',
            'bootstrap/cache/',
            'vendor/',
            'node_modules/',
            'credentials/',
            'public/storage/',
            'database/database.sqlite',
        ];

        return array_values(array_unique(array_merge($defaults, array_filter((array) $configured))));
    }

    protected function parseRepository($repoUrl)
    {
        $repoUrl = trim((string) $repoUrl);
        if ($repoUrl === '') {
            throw new RuntimeException('URL repository GitHub belum diatur.');
        }

        if (!preg_match('~github\.com[/:]([^/]+)/([^/.]+)(?:\.git)?/?$~i', $repoUrl, $matches)) {
            throw new RuntimeException('Format URL repository GitHub tidak valid.');
        }

        return [
            'owner' => $matches[1],
            'repo' => $matches[2],
        ];
    }

    protected function githubRequest()
    {
        $request = Http::timeout(120)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'WhastApp-Github-Updater',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);

        $token = (string) config('services.github_updater.token', '');
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }

    protected function formatGithubError($statusCode, $message = null)
    {
        $message = trim((string) $message);

        if ((int) $statusCode === 404) {
            return $message !== ''
                ? 'Repo GitHub tidak bisa diakses: ' . $message . '. Jika repo private, isi token GitHub di .env.'
                : 'Repo GitHub tidak ditemukan atau private. Jika repo private, isi token GitHub di .env.';
        }

        if ((int) $statusCode === 401 || (int) $statusCode === 403) {
            return $message !== ''
                ? 'Akses ke GitHub ditolak: ' . $message . '. Periksa token GitHub.'
                : 'Akses ke GitHub ditolak. Periksa token GitHub.';
        }

        return $message !== ''
            ? sprintf('GitHub mengembalikan error %s: %s', $statusCode, $message)
            : sprintf('GitHub mengembalikan error %s.', $statusCode);
    }

    protected function prepareWorkingDirectory()
    {
        $directory = storage_path('app/updater/tmp/' . now()->format('Ymd-His') . '-' . Str::random(6));
        $this->ensureDirectory($directory);

        return $directory;
    }

    protected function prepareBackupDirectory()
    {
        $directory = storage_path('app/updater/backups/' . now()->format('Ymd-His'));
        $this->ensureDirectory($directory);

        return $directory;
    }

    protected function ensureDirectory($path)
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    protected function getMirrorPath()
    {
        $configured = trim((string) config('services.github_updater.mirror_path', ''));
        if ($configured === '') {
            return null;
        }

        if (preg_match('~^[A-Za-z]:[\\\\/]~', $configured)) {
            return $configured;
        }

        return base_path($configured);
    }

    protected function isMirrorEnabled()
    {
        $mirrorPath = $this->getMirrorPath();

        return !empty($mirrorPath) && File::exists($mirrorPath) && File::isDirectory($mirrorPath);
    }

    protected function readLastSync()
    {
        $path = storage_path('app/updater/last-sync.json');
        if (!File::exists($path)) {
            return null;
        }

        return json_decode(File::get($path), true);
    }

    protected function writeLastSync(array $payload)
    {
        $path = storage_path('app/updater/last-sync.json');
        $this->ensureDirectory(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function resolvePendingChanges(array $remote, $lastSync)
    {
        $summary = [
            'total' => 0,
            'added' => 0,
            'modified' => 0,
            'removed' => 0,
            'renamed' => 0,
        ];

        $lastCommit = data_get($lastSync, 'latest_commit');
        if (!empty($lastCommit) && $lastCommit === $remote['latest_commit']) {
            return [
                'files' => [],
                'summary' => $summary,
                'is_up_to_date' => true,
            ];
        }

        $files = [];
        if (!empty($lastCommit)) {
            $compareData = $this->githubJson(sprintf(
                'repos/%s/%s/compare/%s...%s',
                $remote['owner'],
                $remote['repo'],
                rawurlencode($lastCommit),
                rawurlencode($remote['latest_commit'])
            ));

            $files = $this->normalizeFiles(data_get($compareData, 'files', []));
        } else {
            $files = $remote['latest_files'] ?? [];
        }

        foreach ($files as $file) {
            $summary['total']++;
            if (isset($summary[$file['status']])) {
                $summary[$file['status']]++;
            } else {
                $summary['modified']++;
            }
        }

        return [
            'files' => array_slice($files, 0, 30),
            'summary' => $summary,
            'is_up_to_date' => false,
        ];
    }

    protected function normalizeFiles(array $files)
    {
        return collect($files)
            ->map(function ($file) {
                $status = strtolower((string) data_get($file, 'status', 'modified'));

                if (in_array($status, ['changed', 'copied'], true)) {
                    $status = 'modified';
                }

                if (!in_array($status, ['added', 'modified', 'removed', 'renamed'], true)) {
                    $status = 'modified';
                }

                return [
                    'path' => (string) data_get($file, 'filename', ''),
                    'status' => $status,
                ];
            })
            ->filter(fn ($file) => $file['path'] !== '')
            ->values()
            ->all();
    }
}
