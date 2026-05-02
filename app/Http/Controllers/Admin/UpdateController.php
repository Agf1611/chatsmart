<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GithubUpdaterService;
use Throwable;

class UpdateController extends Controller
{
    public function index(GithubUpdaterService $updater)
    {
        $updaterStatus = $updater->getStatus();

        return view('pages.admin.update', compact('updaterStatus'));
    }

    public function sync(GithubUpdaterService $updater)
    {
        try {
            $result = $updater->sync();

            return redirect()->route('admin.update')->with('alert', [
                'type' => 'success',
                'msg' => sprintf(
                    'Update berhasil [%s]. File baru: %d, file diupdate: %d, file dilewati: %d, file tidak berubah: %d, file tersalin ke repo lokal: %d.',
                    $result['latest_commit_short'],
                    $result['new_files'],
                    $result['updated_files'],
                    $result['skipped_files'],
                    $result['unchanged_files'],
                    $result['mirrored_files']
                ),
            ]);
        } catch (Throwable $e) {
            return redirect()->route('admin.update')->with('alert', [
                'type' => 'danger',
                'msg' => 'Update GitHub gagal: ' . $e->getMessage(),
            ]);
        }
    }
}
