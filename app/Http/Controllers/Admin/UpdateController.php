<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GithubUpdaterService;
use Illuminate\Support\Facades\File;
use Throwable;

class UpdateController extends Controller
{
    public function index(GithubUpdaterService $updater)
    {
        $release = [
            'app_version' => data_get(json_decode(File::get(base_path('composer.json')), true), 'version', 'unknown'),
            'node_version' => data_get(json_decode(File::get(base_path('package.json')), true), 'version', 'unknown'),
            'server_type' => env('TYPE_SERVER', 'localhost'),
            'node_url' => env('WA_URL_SERVER'),
            'node_port' => env('PORT_NODE'),
        ];

        $updaterStatus = $updater->getStatus();

        return view('pages.admin.update', compact('release', 'updaterStatus'));
    }

    public function sync(GithubUpdaterService $updater)
    {
        try {
            $result = $updater->sync();

            return redirect()->route('admin.update')->with('alert', [
                'type' => 'success',
                'msg' => sprintf(
                    'Update berhasil dari %s [%s]. File baru: %d, file diupdate: %d, file dilewati: %d, file tidak berubah: %d.',
                    $result['repository'],
                    $result['latest_commit_short'],
                    $result['new_files'],
                    $result['updated_files'],
                    $result['skipped_files'],
                    $result['unchanged_files']
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
