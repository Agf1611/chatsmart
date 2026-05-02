<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DatabaseToolController extends Controller
{
    protected $databaseBackupService;

    public function __construct(DatabaseBackupService $databaseBackupService)
    {
        $this->databaseBackupService = $databaseBackupService;
    }

    public function index()
    {
        return view('pages.admin.database-tools', [
            'status' => $this->databaseBackupService->getStatus(),
            'backups' => $this->databaseBackupService->listBackups(),
        ]);
    }

    public function createBackup(Request $request)
    {
        try {
            $fileName = $this->databaseBackupService->createBackup($request->input('label'));

            return back()->with('alert', [
                'type' => 'success',
                'msg' => 'Backup database berhasil dibuat: ' . $fileName,
            ]);
        } catch (ProcessFailedException $exception) {
            return backWithFlash('danger', 'Backup gagal dijalankan: ' . $exception->getProcess()->getErrorOutput());
        } catch (RuntimeException $exception) {
            return backWithFlash('danger', $exception->getMessage());
        } catch (\Throwable $exception) {
            return backWithFlash('danger', 'Backup database gagal dibuat.');
        }
    }

    public function restore(Request $request)
    {
        $request->validate([
            'confirmation_text' => ['required', 'in:RESTORE'],
            'create_safety_backup' => ['nullable', 'boolean'],
            'backup_file' => ['nullable', 'string'],
            'upload_file' => ['nullable', 'file', 'mimes:sql,txt', 'max:51200'],
        ]);

        try {
            $createSafetyBackup = $request->boolean('create_safety_backup', true);

            if ($request->hasFile('upload_file')) {
                $result = $this->databaseBackupService->restoreFromUploadedBackup($request->file('upload_file'), $createSafetyBackup);
            } elseif ($request->filled('backup_file')) {
                $result = $this->databaseBackupService->restoreFromExistingBackup($request->input('backup_file'), $createSafetyBackup);
            } else {
                return backWithFlash('danger', 'Pilih file backup atau upload file .sql terlebih dahulu.');
            }

            $message = 'Restore database berhasil dari ' . $result['restored_from'] . '.';
            if (!empty($result['safety_backup'])) {
                $message .= ' Safety backup dibuat: ' . $result['safety_backup'] . '.';
            }

            return back()->with('alert', [
                'type' => 'success',
                'msg' => $message,
            ]);
        } catch (ProcessFailedException $exception) {
            return backWithFlash('danger', 'Restore gagal dijalankan: ' . $exception->getProcess()->getErrorOutput());
        } catch (RuntimeException $exception) {
            return backWithFlash('danger', $exception->getMessage());
        } catch (\Throwable $exception) {
            return backWithFlash('danger', 'Restore database gagal dijalankan.');
        }
    }

    public function download(Request $request)
    {
        return $this->databaseBackupService->getDownloadResponse($request->query('file'));
    }

    public function delete(Request $request)
    {
        $request->validate([
            'backup_file' => ['required', 'string'],
        ]);

        try {
            $this->databaseBackupService->deleteBackup($request->input('backup_file'));

            return back()->with('alert', [
                'type' => 'success',
                'msg' => 'File backup berhasil dihapus.',
            ]);
        } catch (RuntimeException $exception) {
            return backWithFlash('danger', $exception->getMessage());
        } catch (\Throwable $exception) {
            return backWithFlash('danger', 'File backup gagal dihapus.');
        }
    }
}
