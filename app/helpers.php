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
            ->post(getNodeRuntimeInternalUrl() . '/backend-clearCache');
        return true;
    } catch (\Throwable $th) {
        return false;
    }
}

function isLocalOrPrivateHost(string $host): bool
{
    $host = trim(strtolower($host), '[]');
    if ($host === '') {
        return false;
    }

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        return str_ends_with($host, '.local');
    }

    return filter_var(
        $host,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;
}

function normalizeUrlOrigin(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return rtrim($url, '/');
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return rtrim($origin, '/');
}

function resolveInstallerServerDefaults(\Illuminate\Http\Request $request, $defaultPort = 3100): array
{
    $port = (string) $defaultPort;
    $appUrl = rtrim((string) $request->root(), '/');
    $host = (string) $request->getHost();
    $isLocal = isLocalOrPrivateHost($host);
    $serverType = $isLocal ? 'localhost' : 'hosting';
    $publicNodeUrl = $isLocal ? 'http://127.0.0.1:' . $port : $appUrl;
    $internalNodeUrl = 'http://127.0.0.1:' . $port;

    $corsOrigins = array_values(array_unique(array_filter([
        normalizeUrlOrigin($appUrl),
        normalizeUrlOrigin($publicNodeUrl),
        $isLocal ? 'http://localhost' : null,
        $isLocal ? 'http://127.0.0.1' : null,
    ])));

    return [
        'APP_URL' => $appUrl,
        'TYPE_SERVER' => $serverType,
        'PORT_NODE' => $port,
        'WA_URL_SERVER' => $publicNodeUrl,
        'WA_URL_SERVER_PUBLIC' => $publicNodeUrl,
        'WA_URL_SERVER_INTERNAL' => $internalNodeUrl,
        'CORS_ALLOWED_ORIGINS' => implode(',', $corsOrigins),
        'ORIGIN' => normalizeUrlOrigin($appUrl),
        'SESSION_SECURE_COOKIE' => $request->isSecure() ? 'true' : 'false',
        'WA_CREDENTIALS_PATH' => getNodeCredentialsBasePath(),
    ];
}

function generateAppKeyValue(): string
{
    return 'base64:' . base64_encode(random_bytes(32));
}

function ensureEnvFileExists(): bool
{
    $envPath = base_path('.env');
    if (file_exists($envPath)) {
        return true;
    }

    $examplePath = base_path('.env.example');
    if (file_exists($examplePath)) {
        return writeFileAtomically($envPath, file_get_contents($examplePath) ?: '');
    }

    return writeFileAtomically($envPath, '');
}

function ensureAppKeyExists(): string
{
    $existingKey = getEnvValue('APP_KEY', (string) config('app.key'));
    if (!empty($existingKey)) {
        config(['app.key' => $existingKey]);

        return $existingKey;
    }

    $generatedKey = generateAppKeyValue();
    if (!setEnv('APP_KEY', $generatedKey)) {
        throw new RuntimeException('APP_KEY gagal dibuat. Pastikan file .env bisa ditulis.');
    }

    config(['app.key' => $generatedKey]);

    return $generatedKey;
}

function getNodeRuntimePublicUrl(): string
{
    $publicUrl = trim((string) getEnvValue('WA_URL_SERVER_PUBLIC', ''));
    if ($publicUrl === '') {
        $publicUrl = trim((string) getEnvValue('WA_URL_SERVER', (string) env('WA_URL_SERVER', '')));
    }

    return rtrim($publicUrl, '/');
}

function getNodeRuntimeInternalUrl(): string
{
    $internalUrl = trim((string) getEnvValue('WA_URL_SERVER_INTERNAL', ''));
    if ($internalUrl === '') {
        $internalUrl = getNodeRuntimePublicUrl();
    }

    return rtrim($internalUrl, '/');
}

function getNodeCredentialsBasePath(): string
{
    $configuredPath = trim((string) getEnvValue('WA_CREDENTIALS_PATH', ''));
    if ($configuredPath === '') {
        $configuredPath = storage_path('app/wa-sessions');
    }

    return rtrim($configuredPath, DIRECTORY_SEPARATOR . '/\\');
}

function getNodeCredentialPath(?string $token = null): string
{
    $basePath = getNodeCredentialsBasePath();
    if ($token === null || $token === '') {
        return $basePath;
    }

    return $basePath . DIRECTORY_SEPARATOR . trim((string) $token, DIRECTORY_SEPARATOR . '/\\');
}

function writeFileAtomically(string $path, string $contents): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }

    $tempPath = tempnam($directory, 'tmp');
    if ($tempPath === false) {
        return false;
    }

    $bytes = @file_put_contents($tempPath, $contents, LOCK_EX);
    if ($bytes === false) {
        @unlink($tempPath);
        return false;
    }

    if (file_exists($path)) {
        @chmod($tempPath, fileperms($path) & 0777);
    }

    if (@rename($tempPath, $path)) {
        return true;
    }

    $copied = @copy($tempPath, $path);
    @unlink($tempPath);

    return $copied;
}

function canWritePath(string $path): bool
{
    if (file_exists($path)) {
        return is_writable($path);
    }

    $directory = dirname($path);
    while (!file_exists($directory)) {
        $parent = dirname($directory);
        if ($parent === $directory) {
            break;
        }

        $directory = $parent;
    }

    return is_writable($directory);
}

function getInstallerFilesystemStatus(): array
{
    return [
        [
            'label' => '.env',
            'path' => base_path('.env'),
            'writable' => canWritePath(base_path('.env')),
        ],
        [
            'label' => 'storage/app',
            'path' => storage_path('app'),
            'writable' => canWritePath(storage_path('app')),
        ],
        [
            'label' => 'bootstrap/cache',
            'path' => base_path('bootstrap/cache'),
            'writable' => canWritePath(base_path('bootstrap/cache')),
        ],
        [
            'label' => 'storage/app/wa-sessions',
            'path' => getNodeCredentialsBasePath(),
            'writable' => canWritePath(getNodeCredentialsBasePath()),
        ],
    ];
}

function ensureInstallerFilesystemReady(): void
{
    $unwritablePaths = array_filter(getInstallerFilesystemStatus(), function ($item) {
        return !$item['writable'];
    });

    if ($unwritablePaths === []) {
        return;
    }

    $labels = array_map(function ($item) {
        return $item['label'] . ' (' . $item['path'] . ')';
    }, $unwritablePaths);

    throw new RuntimeException(
        'Installer butuh permission tulis ke: ' . implode(', ', $labels) . '.'
    );
}

function setEnv(string $key, ?string $value): bool
{
    $path = base_path('.env');
    if (!ensureEnvFileExists()) {
        return false;
    }

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

    return writeFileAtomically($path, implode(PHP_EOL, $lines) . PHP_EOL);
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

function getInstallLockPaths(): array
{
    return [
        storage_path('app/install.lock'),
        base_path('bootstrap/cache/install.lock'),
    ];
}

function getInstallLockPath(): string
{
    return getInstallLockPaths()[0];
}

function hasInstallLock(): bool
{
    foreach (getInstallLockPaths() as $path) {
        if (file_exists($path)) {
            return true;
        }
    }

    return false;
}

function writeInstallLock(array $payload = []): bool
{
    $payload = array_merge([
        'installed_at' => now()->toDateTimeString(),
        'app_url' => config('app.url'),
    ], $payload);

    $contents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $written = false;

    foreach (getInstallLockPaths() as $path) {
        $written = writeFileAtomically($path, $contents) || $written;
    }

    return $written;
}

function removeInstallLock(): bool
{
    $removed = true;

    foreach (getInstallLockPaths() as $path) {
        if (file_exists($path) && !@unlink($path)) {
            $removed = false;
        }
    }

    return $removed;
}

function backWithFlash($type, $message)
{
    return redirect()->back()->with('alert', ['type' => $type, 'msg' => $message]);
}

function redirectWithFlash($type, $message, $url)
{
    return redirect($url)->with('alert', ['type' => $type, 'msg' => $message]);
}
