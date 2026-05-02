<?php


namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        $urlnode =
            $request->typeServer === 'other'
            ? $request->urlnode . ':' . $request->portnode
            : ($request->typeServer === 'hosting'
                ? url('/')
                : 'http://localhost:' . $request->portnode);
        setEnv('TYPE_SERVER', $request->typeServer);
        setEnv('PORT_NODE', $request->portnode);
        setEnv('WA_URL_SERVER', $urlnode);
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
            return redirect('/');
        }
        if ($request->method() === 'POST') {

                $request->validate([
                    'database.host' => 'required|string',
                    'database.username' => 'required|string',
                    'database.password' => 'nullable|string',
                    'database.database' => 'required|string',
                    //'licensekey'           => 'required',
                    //'buyeremail'           =>'required|email',
                    'admin.username' => 'required',
                    'admin.email' => 'required|email',
                    'admin.password' => 'required|max:255',
                ]);

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

                // delete old tables
                DB::transaction(function () {
                    DB::unprepared(
                        File::get(base_path('database/db_tables.sql'))
                    );
                });
                // cache clear artisan
                Artisan::call('cache:clear');
            } catch (\Throwable $th) {
                Artisan::call('migrate:fresh', [
                    '--force' => true,
                ]);
            }
            /** SETTING .ENV VARS STARTS **/
            $urll = rtrim($request->root(), '/');
            $env['DB_HOST'] = $db_params['host'];
            $env['DB_DATABASE'] = $db_params['database'];
            $env['DB_USERNAME'] = $db_params['username'];
            $env['DB_PASSWORD'] = $db_params['password'] ?? '';
            $env['APP_URL'] = $urll;
            $env['APP_INSTALLED'] = 'true';
            if ($request->input('licensekey') != null) {
                $env['LICENSE_KEY'] = $request->input('licensekey');
            }
            if ($request->input('buyeremail') != null) {
                $env['BUYER_EMAIL'] = $request->input('buyeremail');
            }


            foreach ($env as $k => &$v) {
                setEnv($k, $v);
            }

            /** SETTING .ENV VARS ENDS **/

            /** CREATE ADMIN USER STARTS **/
            if (
                !($user = User::where(
                    'email',
                    $request->input('admin.email')
                )->first())
            ) {
                $user = new User();
                $user->username = $request->input('admin.username');
                $user->email = $request->input('admin.email');
                $user->password = Hash::make($request->input('admin.password'));
                $user->email_verified_at = date('Y-m-d');
                $user->level = 'admin';
                $user->active_subscription = 'lifetime';
                $user->limit_device = 10;
                $user->chunk_blast = 0;
                $user->save();
            }
            /** CREATE ADMIN USER END **/
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

        return view('install', [
            'requirements' => $requirements,
        ]);
    }
}
