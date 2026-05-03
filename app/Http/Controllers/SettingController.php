<?php


namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin')->except(
            'activate_license',
            'install',
            'test_database_connection'
        );
    }

    private function denyWhenInstalledUnlessAdmin()
    {
        if (!isAppInstalled()) {
            return null;
        }

        if (!Auth::check() || Auth::user()->level !== 'admin') {
            abort(403);
        }

        return null;
    }

    private function installerFilesystemRequirements(): array
    {
        return getInstallerFilesystemStatus();
    }

    public function index()
    {
        $historyCleanup = [
            'enabled' => filter_var(env('MESSAGE_HISTORY_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
            'days' => (int) env('MESSAGE_HISTORY_RETENTION_DAYS', 30),
        ];

        $aiBotSettings = [
            'enabled' => filter_var(env('AI_BOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'openai_key' => env('OPENAI_API_KEY') ? str_repeat('*', 12) : '',
            'gemini_key' => env('GEMINI_API_KEY') ? str_repeat('*', 12) : '',
            'default_timeout' => (int) env('AI_DEFAULT_TIMEOUT', 20),
            'default_max_output' => (int) env('AI_DEFAULT_MAX_OUTPUT', 400),
        ];

        return view('pages.admin.settings', compact('historyCleanup', 'aiBotSettings'));
    }

    public function setServer(Request $request)
    {
        $request->validate([
            'typeServer' => ['required'],
            'portnode' => ['required'],
            'urlnode' => ['required_if:typeServer,other', 'nullable', 'url'],
        ]);
        $normalizedNodeUrl = rtrim((string) $request->urlnode, '/');
        $publicNodeUrl = $request->typeServer === 'other'
            ? $normalizedNodeUrl
            : ($request->typeServer === 'hosting'
                ? rtrim((string) url('/'), '/')
                : 'http://127.0.0.1:' . $request->portnode);
        $internalNodeUrl = 'http://127.0.0.1:' . $request->portnode;
        setEnv('TYPE_SERVER', $request->typeServer);
        setEnv('PORT_NODE', $request->portnode);
        setEnv('WA_URL_SERVER', $publicNodeUrl);
        setEnv('WA_URL_SERVER_PUBLIC', $publicNodeUrl);
        setEnv('WA_URL_SERVER_INTERNAL', $internalNodeUrl);
        return back()->with('alert', [
            'type' => 'success',
            'msg' => 'Success Update configuration!',
        ]);
    }

    public function setHistoryCleanup(Request $request)
    {
        $request->validate([
            'enabled' => ['nullable', 'in:0,1'],
            'days' => ['required', 'integer', 'in:7,30,60,90'],
        ]);

        setEnv('MESSAGE_HISTORY_AUTO_CLEANUP', $request->boolean('enabled') ? 'true' : 'false');
        setEnv('MESSAGE_HISTORY_RETENTION_DAYS', (string) $request->days);

        return back()->with('alert', [
            'type' => 'success',
            'msg' => 'Auto cleanup message history updated.',
        ]);
    }

    public function runHistoryCleanup(Request $request)
    {
        $days = (int) env('MESSAGE_HISTORY_RETENTION_DAYS', 30);

        Artisan::call('messages:cleanup-history', [
            '--days' => $days,
            '--force' => true,
        ]);

        $output = trim(Artisan::output()) ?: 'Cleanup message history finished.';

        return back()->with('alert', [
            'type' => 'success',
            'msg' => $output,
        ]);
    }

    public function setAiBotSettings(Request $request)
    {
        $request->validate([
            'enabled' => ['nullable', 'in:0,1'],
            'openai_api_key' => ['nullable', 'string'],
            'gemini_api_key' => ['nullable', 'string'],
            'default_timeout' => ['required', 'integer', 'min:5', 'max:60'],
            'default_max_output' => ['required', 'integer', 'min:50', 'max:2000'],
        ]);

        setEnv('AI_BOT_ENABLED', $request->boolean('enabled') ? 'true' : 'false');
        if ($request->filled('openai_api_key')) {
            setEnv('OPENAI_API_KEY', trim((string) $request->openai_api_key));
        }
        if ($request->filled('gemini_api_key')) {
            setEnv('GEMINI_API_KEY', trim((string) $request->gemini_api_key));
        }
        setEnv('AI_DEFAULT_TIMEOUT', (string) $request->default_timeout);
        setEnv('AI_DEFAULT_MAX_OUTPUT', (string) $request->default_max_output);

        if (!getEnvValue('AI_INTERNAL_TOKEN')) {
            setEnv('AI_INTERNAL_TOKEN', bin2hex(random_bytes(24)));
        }

        return back()->with('alert', [
            'type' => 'success',
            'msg' => 'AI bot settings updated.',
        ]);
    }

    public function activate_license(Request $request)
    {
        $this->denyWhenInstalledUnlessAdmin();

        return response()->json([
            'status' => true,
            'msg' => 'Legacy license validation has been disabled in this cleaned install.',
        ]);
    }

    public function test_database_connection(Request $request)
    {
        $this->denyWhenInstalledUnlessAdmin();

        $data = json_decode(json_encode($request->database));
        $error_message = null;
        try {
            $db = new \mysqli(
                $data->host,
                $data->username,
                $data->password,
                $data->database
            );
            $error_message = $db->connect_errno
                ? 'Connection Failed .' . $db->connect_error
                : $error_message;
        } catch (\Throwable $th) {
            $error_message = 'Connection failed';
        }
        return response()->json([
            'status' => $error_message ?? 'Success',
            'error' => $error_message === null ? false : true,
        ]);
    }

    public function install(Request $request)
    {
        if (isAppInstalled()) {
            return redirect()->route('login');
        }
        if ($request->method() === 'POST') {
            $request->validate([
                'install_mode' => 'required|in:simple',
                'database.host' => 'required|string',
                'database.username' => 'required|string',
                'database.password' => 'nullable|string',
                'database.database' => 'required|string',
                'admin.username' => 'required|string|max:255',
                'admin.email' => 'required|email|max:255',
                'admin.password' => 'required|string|min:8|max:255',
            ]);

            try {
                ensureInstallerFilesystemReady();
                if (!ensureEnvFileExists()) {
                    throw new \RuntimeException('File .env belum ada dan gagal dibuat otomatis dari .env.example.');
                }
            } catch (\RuntimeException $e) {
                $validator = Validator::make([], [])
                    ->errors()
                    ->add('Installer', $e->getMessage() . ' Periksa permission file/folder di hosting.');

                return back()
                    ->withErrors($validator)
                    ->withInput();
            }

            /** CREATE DATABASE CONNECTION STARTS **/
            $db_params = $request->input('database');
            Config::set(
                'database.connections.mysql',
                array_merge(config('database.connections.mysql'), $db_params)
            );
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                $validator = Validator::make($request->all(), [])
                    ->errors()
                    ->add('Database', $e->getMessage());
                return back()
                    ->withErrors($validator)
                    ->withInput();
            }
            /** CREATE DATABASE CONNECTION ENDS **/
            try {
                $serverDefaults = resolveInstallerServerDefaults($request);
                $env = [
                    'DB_HOST' => $db_params['host'],
                    'DB_DATABASE' => $db_params['database'],
                    'DB_USERNAME' => $db_params['username'],
                    'DB_PASSWORD' => $db_params['password'] ?? '',
                    'APP_INSTALLED' => 'false',
                ];
                $env = array_merge($env, $serverDefaults);

                foreach ($env as $k => $v) {
                    if (!setEnv($k, $v)) {
                        throw new \RuntimeException('Gagal menulis file .env saat menyimpan konfigurasi installer.');
                    }
                }

                ensureAppKeyExists();

                DB::purge('mysql');
                DB::reconnect('mysql');

                Artisan::call('migrate', [
                    '--force' => true,
                ]);

                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('config:clear');
                Artisan::call('route:clear');
            } catch (\Throwable $th) {
                Log::error('Installation failed', [
                    'message' => $th->getMessage(),
                ]);

                $validator = Validator::make([], [])
                    ->errors()
                    ->add('Installer', 'Automatic migration failed: ' . $th->getMessage());

                return back()
                    ->withErrors($validator)
                    ->withInput();
            }

            /** CREATE ADMIN USER STARTS **/
            try {
                $user = User::firstOrNew([
                    'email' => $request->input('admin.email'),
                ]);
                $user->username = $request->input('admin.username');
                $user->password = Hash::make($request->input('admin.password'));
                $user->email_verified_at = now();
                $user->level = 'admin';
                $user->active_subscription = 'lifetime';
                $user->limit_device = 10;
                $user->chunk_blast = 0;
                $user->api_key = $user->api_key ?: Str::random(32);
                $user->save();
            } catch (\Throwable $th) {
                Log::error('Admin user creation failed', [
                    'message' => $th->getMessage(),
                ]);

                $validator = Validator::make([], [])
                    ->errors()
                    ->add('Installer', 'Gagal membuat akun admin: ' . $th->getMessage());

                return back()
                    ->withErrors($validator)
                    ->withInput();
            }
            /** CREATE ADMIN USER END **/

            if (!setEnv('APP_INSTALLED', 'true')) {
                return backWithFlash('error', 'Gagal menandai aplikasi sebagai terinstall. Periksa permission file `.env`.');
            }
            if (!getEnvValue('AUTH')) {
                if (!setEnv('AUTH', bin2hex(random_bytes(16)))) {
                    return backWithFlash('error', 'Gagal menyimpan token AUTH ke file `.env`.');
                }
            }
            if (!getEnvValue('AI_INTERNAL_TOKEN')) {
                if (!setEnv('AI_INTERNAL_TOKEN', bin2hex(random_bytes(24)))) {
                    return backWithFlash('error', 'Gagal menyimpan AI internal token ke file `.env`.');
                }
            }

            if (!writeInstallLock([
                'install_mode' => $request->input('install_mode'),
                'admin_email' => $user->email,
                'database' => $db_params['database'],
            ])) {
                return backWithFlash('error', 'Gagal membuat install lock. Periksa permission `storage/app` atau `bootstrap/cache`.');
            }

            Artisan::call('config:clear');
            clearstatcache();

            if (!isAppInstalled()) {
                return backWithFlash('error', 'Installer tidak bisa memverifikasi status instalasi. Cek permission `.env`, `storage/app`, dan `bootstrap/cache`.');
            }

            Auth::loginUsingId($user->id, true);
            return redirect()->route('home');
        }

        // get method
        $mysql_user_version = [
            'distrib' => '',
            'version' => null,
            'compatible' => false,
        ];

        if (function_exists('mysqli_get_client_info')) {
            $clientInfo = mysqli_get_client_info();
            $mysql_user_version['distrib'] = 'mysqli-client';
            $mysql_user_version['compatible'] = true;

            if (preg_match('/(\d+(?:\.\d+)+)/', $clientInfo, $matches)) {
                $mysql_user_version['version'] = $matches[1];
            }
        }

        $requirements = [
            'php' => ['version' => "8.0", 'current' => phpversion()],
            'mysql' => ['version' => 5.6, 'current' => $mysql_user_version],
            'php_extensions' => [
                'curl' => false,
                'fileinfo' => false,
                'intl' => false,
                'json' => false,
                'mbstring' => false,
                'openssl' => false,
                'mysqli' => false,
                'zip' => false,
                'ctype' => false,
                'dom' => false,
            ],
        ];

        $php_loaded_extensions = get_loaded_extensions();


        foreach ($requirements['php_extensions'] as $name => &$enabled) {
            $enabled = in_array($name, $php_loaded_extensions);
        }

        $filesystemRequirements = $this->installerFilesystemRequirements();

        return view('install', [
            'requirements' => $requirements,
            'filesystemRequirements' => $filesystemRequirements,
        ]);
    }
}
