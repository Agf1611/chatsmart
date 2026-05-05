<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class AiDiagnosticService
{
    public function record(string $level, string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => now()->toDateTimeString(),
            'level' => strtolower($level),
            'message' => $message,
            'context' => $this->sanitizeContext($context),
        ];

        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));
        File::append($path, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    public function recent(int $limit = 20): array
    {
        $path = $this->path();
        if (!File::exists($path)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim((string) File::get($path))) ?: [];
        $lines = array_values(array_filter($lines));
        $slice = array_slice($lines, -1 * max(1, $limit));

        $items = [];
        foreach (array_reverse($slice) as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $items[] = [
                'timestamp' => $decoded['timestamp'] ?? null,
                'level' => $decoded['level'] ?? 'info',
                'message' => $decoded['message'] ?? 'Unknown AI diagnostic event.',
                'context' => is_array($decoded['context'] ?? null) ? $decoded['context'] : [],
            ];
        }

        return $items;
    }

    public function path(): string
    {
        return storage_path('logs/ai-diagnostics.log');
    }

    protected function sanitizeContext(array $context): array
    {
        $filtered = [];

        foreach ($context as $key => $value) {
            $keyString = strtolower((string) $key);
            if (str_contains($keyString, 'token') || str_contains($keyString, 'key') || str_contains($keyString, 'authorization')) {
                continue;
            }

            if (is_array($value)) {
                $filtered[$key] = $this->sanitizeContext($value);
                continue;
            }

            if (is_object($value)) {
                $filtered[$key] = '[object]';
                continue;
            }

            $filtered[$key] = is_string($value)
                ? mb_strimwidth($value, 0, 500, '...')
                : $value;
        }

        return $filtered;
    }
}
