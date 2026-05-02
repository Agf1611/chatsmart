<?php

use Illuminate\Support\Facades\Http;

function str_extract($str, $pattern, $get = null, $default = null)
{
    $result = [];
    preg_match($pattern, $str, $matches);
    preg_match_all('/(\(\?P\<(?P<name>.+)\>\.\+\)+)/U', $pattern, $captures);
    $names = $captures['name'] ?? [];
    foreach ($names as $name) {
        $result[$name] = $matches[$name] ?? null;
    }
    return $get ? $result[$get] ?? $default : $result;
}

function wrap_str($str = '', $first_delimiter = "'", $last_delimiter = null)
{
    if (!$last_delimiter) {
        return $first_delimiter . $str . $first_delimiter;
    }

    return $first_delimiter . $str . $last_delimiter;
}

function getExtensionImageFromUrl($url)
{
    $url = explode('.', $url);
    $extension = end($url);
    return $extension;
}

function normalizePhoneNumber($value)
{
    $digits = preg_replace('/\D+/', '', (string) $value);
    if (!$digits) {
        return '';
    }

    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '0')) {
        return '62' . substr($digits, 1);
    }

    if (str_starts_with($digits, '8')) {
        return '62' . $digits;
    }

    return $digits;
}

function clearCacheNode()
{
    try {
        Http::withOptions(['verify' => false])
            ->asForm()
            ->post(env('WA_URL_SERVER') . '/backend-clearCache');
        return true;
    } catch (\Throwable $th) {
        return false;
    }
}

function setEnv(string $key, ?string $value)
{
    $path = base_path('.env');
    $lines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $updated = false;
    $value = $value ?? '';
    $escapedValue = preg_match('/\s/', $value) ? '"' . addcslashes($value, '"') . '"' : $value;

    foreach ($lines as &$line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$currentKey] = explode('=', $line, 2);
        if ($currentKey === $key) {
            $line = "{$key}={$escapedValue}";
            $updated = true;
        }
    }

    if (!$updated) {
        $lines[] = "{$key}={$escapedValue}";
    }

    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

function getEnvValue(string $key, ?string $default = null): ?string
{
    $path = base_path('.env');
    if (!file_exists($path)) {
        return $default;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$currentKey, $currentValue] = explode('=', $line, 2);
        if ($currentKey !== $key) {
            continue;
        }

        return trim($currentValue, "\"'");
    }

    return $default;
}

function isAppInstalled(): bool
{
    return filter_var(
        getEnvValue('APP_INSTALLED', (string) env('APP_INSTALLED', false)),
        FILTER_VALIDATE_BOOLEAN
    ) || hasInstallLock();
}

function getInstallLockPath(): string
{
    return storage_path('app/install.lock');
}

function hasInstallLock(): bool
{
    return file_exists(getInstallLockPath());
}

function writeInstallLock(array $payload = []): bool
{
    $payload = array_merge([
        'installed_at' => now()->toDateTimeString(),
        'app_url' => config('app.url'),
    ], $payload);

    if (!is_dir(dirname(getInstallLockPath()))) {
        mkdir(dirname(getInstallLockPath()), 0755, true);
    }

    return (bool) file_put_contents(
        getInstallLockPath(),
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function removeInstallLock(): bool
{
    if (!hasInstallLock()) {
        return true;
    }

    return unlink(getInstallLockPath());
}

function backWithFlash($type, $message)
{
    return redirect()->back()->with('alert', ['type' => $type, 'msg' => $message]);
}

function redirectWithFlash($type, $message, $url)
{
    return redirect($url)->with('alert', ['type' => $type, 'msg' => $message]);
}
