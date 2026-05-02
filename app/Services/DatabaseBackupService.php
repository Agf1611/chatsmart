<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function getStatus()
    {
        return [
            'connection' => Config::get('database.default'),
            'database' => Config::get('database.connections.mysql.database'),
            'host' => Config::get('database.connections.mysql.host'),
            'port' => Config::get('database.connections.mysql.port'),
            'username' => Config::get('database.connections.mysql.username'),
            'backup_dir' => $this->ensureBackupDirectory(),
            'mysqldump' => $this->findBinary('mysqldump'),
            'mysql' => $this->findBinary('mysql'),
        ];
    }

    public function listBackups()
    {
        $directory = $this->ensureBackupDirectory();
        $files = collect(File::files($directory))
            ->filter(function ($file) {
                return strtolower($file->getExtension()) === 'sql';
            })
            ->sortByDesc(function ($file) {
                return $file->getMTime();
            })
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'path' => $file->getRealPath(),
                    'size' => $file->getSize(),
                    'size_human' => $this->humanFileSize($file->getSize()),
                    'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            })
            ->values();

        return $files;
    }

    public function createBackup($label = null)
    {
        $fileName = $this->makeBackupFilename($label);
        $filePath = $this->resolveBackupPath($fileName, false);
        $dumpPath = $this->findBinary('mysqldump');

        $arguments = [
            $dumpPath,
            '--host=' . Config::get('database.connections.mysql.host'),
            '--port=' . Config::get('database.connections.mysql.port'),
            '--user=' . Config::get('database.connections.mysql.username'),
            '--default-character-set=utf8mb4',
            '--skip-comments',
            '--single-transaction',
            '--quick',
        ];

        $password = (string) Config::get('database.connections.mysql.password', '');
        if ($password !== '') {
            $arguments[] = '--password=' . $password;
        }

        $arguments[] = Config::get('database.connections.mysql.database');

        $process = new Process($arguments, base_path(), null, null, 300);
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Gagal membuat file backup.');
        }

        try {
            $process->run(function ($type, $buffer) use ($handle) {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });
        } finally {
            fclose($handle);
        }

        if (!$process->isSuccessful()) {
            File::delete($filePath);
            throw new ProcessFailedException($process);
        }

        return $fileName;
    }

    public function restoreFromExistingBackup($fileName, $createSafetyBackup = true)
    {
        $backupPath = $this->resolveBackupPath($fileName);
        return $this->restoreFromPath($backupPath, $createSafetyBackup);
    }

    public function restoreFromUploadedBackup(UploadedFile $file, $createSafetyBackup = true)
    {
        $storedName = 'uploaded-restore-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.sql';
        $directory = $this->ensureBackupDirectory();
        $file->move($directory, $storedName);

        return $this->restoreFromPath($this->resolveBackupPath($storedName), $createSafetyBackup, $storedName);
    }

    public function deleteBackup($fileName)
    {
        $filePath = $this->resolveBackupPath($fileName);
        File::delete($filePath);
    }

    public function getDownloadResponse($fileName)
    {
        $filePath = $this->resolveBackupPath($fileName);
        return response()->download($filePath, basename($filePath));
    }

    protected function restoreFromPath($filePath, $createSafetyBackup = true, $sourceName = null)
    {
        $mysqlPath = $this->findBinary('mysql');
        $safetyBackup = null;

        if ($createSafetyBackup) {
            $safetyBackup = $this->createBackup('pre-restore');
        }

        $arguments = [
            $mysqlPath,
            '--host=' . Config::get('database.connections.mysql.host'),
            '--port=' . Config::get('database.connections.mysql.port'),
            '--user=' . Config::get('database.connections.mysql.username'),
            '--default-character-set=utf8mb4',
            Config::get('database.connections.mysql.database'),
        ];

        $password = (string) Config::get('database.connections.mysql.password', '');
        if ($password !== '') {
            $arguments[] = '--password=' . $password;
        }

        $process = new Process($arguments, base_path(), null, null, 600);
        $process->setInput(fopen($filePath, 'rb'));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        return [
            'restored_from' => $sourceName ?: basename($filePath),
            'safety_backup' => $safetyBackup,
        ];
    }

    protected function ensureBackupDirectory()
    {
        $directory = storage_path('app/backups/database');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }

    protected function makeBackupFilename($label = null)
    {
        $database = Str::slug((string) Config::get('database.connections.mysql.database', 'database'));
        $suffix = $label ? '-' . Str::slug($label) : '';

        return $database . $suffix . '-' . now()->format('Ymd-His') . '.sql';
    }

    protected function resolveBackupPath($fileName, $mustExist = true)
    {
        $safeName = basename((string) $fileName);
        $path = $this->ensureBackupDirectory() . DIRECTORY_SEPARATOR . $safeName;

        if ($mustExist && !File::exists($path)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('Hanya file .sql yang diizinkan.');
        }

        return $path;
    }

    protected function findBinary($binary)
    {
        $envKey = $binary === 'mysqldump' ? 'DB_MYSQLDUMP_PATH' : 'DB_MYSQL_PATH';
        $candidates = array_filter([
            env($envKey),
            $binary === 'mysqldump' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'C:\\xampp\\mysql\\bin\\mysql.exe',
            $binary . '.exe',
            $binary,
        ]);

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }

            if (in_array($candidate, [$binary, $binary . '.exe'], true)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf('Executable %s tidak ditemukan.', $binary));
    }

    protected function humanFileSize($bytes)
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
