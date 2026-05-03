<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));

$ensureBootstrapEnv = static function () {
    $basePath = dirname(__DIR__);
    $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
    $examplePath = $basePath . DIRECTORY_SEPARATOR . '.env.example';

    if (!file_exists($envPath) && file_exists($examplePath) && is_readable($examplePath) && is_writable($basePath)) {
        @copy($examplePath, $envPath);
    }

    if (!file_exists($envPath) || !is_readable($envPath) || !is_writable($envPath)) {
        return;
    }

    $contents = @file_get_contents($envPath);
    if ($contents === false) {
        return;
    }

    if (preg_match('/^APP_KEY\s*=\s*(.+)$/m', $contents, $matches) && trim($matches[1], " \t\n\r\0\x0B\"'") !== '') {
        return;
    }

    try {
        $generatedKey = 'base64:' . base64_encode(random_bytes(32));
    } catch (Throwable $e) {
        return;
    }
    if (preg_match('/^APP_KEY\s*=.*$/m', $contents)) {
        $updatedContents = preg_replace('/^APP_KEY\s*=.*$/m', 'APP_KEY=' . $generatedKey, $contents, 1);
    } else {
        $updatedContents = rtrim($contents) . PHP_EOL . 'APP_KEY=' . $generatedKey . PHP_EOL;
    }

    if (is_string($updatedContents)) {
        @file_put_contents($envPath, $updatedContents);
    }
};

$ensureBootstrapEnv();

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
