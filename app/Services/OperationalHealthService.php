<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\Autoreply;
use App\Models\Device;
use App\Models\IncomingMessageLog;
use App\Models\MessageHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OperationalHealthService
{
    public function buildDashboardSummary(?User $user = null, ?Device $selectedDevice = null): array
    {
        $metrics = $this->buildMetrics($user);
        $health = $this->buildHealthSnapshot($user, $metrics['connected_devices']);

        return [
            'health' => $health,
            'metrics' => $metrics,
            'alerts' => $this->buildAlerts($health, $metrics, $selectedDevice),
            'setup' => $this->buildSetupChecklist($user, $metrics, $health),
        ];
    }

    public function buildOperationalAudit(?User $user = null): array
    {
        $metrics = $this->buildMetrics($user);
        $health = $this->buildHealthSnapshot($user, $metrics['connected_devices']);
        $devices = $this->deviceQuery($user)
            ->withCount([
                'autoreplies as active_autoreplies_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'aiConversations as paused_conversations_count' => function ($query) {
                    $query->where('status', 'paused');
                },
            ])
            ->orderBy('body')
            ->get();

        $recentPaused = AiConversation::query()
            ->when($user, function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'paused')
            ->with(['bot', 'device'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return [
            'health' => $health,
            'metrics' => $metrics,
            'alerts' => $this->buildAlerts($health, $metrics),
            'devices' => $devices,
            'recentPaused' => $recentPaused,
        ];
    }

    protected function buildMetrics(?User $user = null): array
    {
        $today = now()->toDateString();

        $deviceQuery = $this->deviceQuery($user);
        $connectedDevices = (clone $deviceQuery)->where('status', 'Connected')->count();
        $disconnectedDevices = (clone $deviceQuery)->where('status', '!=', 'Connected')->count();

        $incomingQuery = IncomingMessageLog::query()
            ->when($user, function (Builder $query) use ($user) {
                $query->whereHas('device', function (Builder $deviceQuery) use ($user) {
                    $deviceQuery->where('user_id', $user->id);
                });
            })
            ->whereDate('last_received_at', $today);

        $historyQuery = MessageHistory::query()
            ->when($user, function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereDate('created_at', $today);

        $aiEventQuery = AiConversationMessage::query()
            ->when($user, function (Builder $query) use ($user) {
                $query->whereHas('conversation', function (Builder $conversationQuery) use ($user) {
                    $conversationQuery->where('user_id', $user->id);
                });
            })
            ->where('role', 'event')
            ->whereDate('created_at', $today);

        $pausedQuery = AiConversation::query()
            ->when($user, function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'paused');

        $autoreplyQuery = Autoreply::query()
            ->when($user, function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            });

        return [
            'connected_devices' => $connectedDevices,
            'disconnected_devices' => $disconnectedDevices,
            'incoming_active_chats_today' => (clone $incomingQuery)->count(),
            'incoming_messages_tracked_today' => (int) (clone $incomingQuery)->sum('message_count'),
            'auto_reply_success_today' => (clone $historyQuery)
                ->where('note', 'like', 'Auto reply:%')
                ->where('status', 'success')
                ->count(),
            'auto_reply_failed_today' => (clone $historyQuery)
                ->where('note', 'like', 'Auto reply:%')
                ->where('status', 'failed')
                ->count(),
            'manual_or_test_sent_today' => (clone $historyQuery)
                ->whereNotNull('send_by')
                ->whereNotIn('note', ['Webhook auto reply'])
                ->where(function (Builder $query) {
                    $query->whereNull('note')
                        ->orWhere('note', 'not like', 'Auto reply:%');
                })
                ->count(),
            'ai_fallback_today' => (clone $aiEventQuery)->count(),
            'paused_conversations' => (clone $pausedQuery)->count(),
            'interactive_rules' => (clone $autoreplyQuery)->whereIn('type', ['list', 'button', 'template'])->count(),
            'interactive_rules_with_fallback' => (clone $autoreplyQuery)
                ->whereIn('type', ['list', 'button', 'template'])
                ->where('transport_policy', 'text_fallback')
                ->count(),
        ];
    }

    protected function buildHealthSnapshot(?User $user = null, int $connectedDevices = 0): array
    {
        $dbHealthy = $this->checkDatabase();
        $laravelHealthy = $this->checkLaravel();
        $nodeHealthy = $this->checkNodeRuntime($connectedDevices);
        $aiHealthy = $this->checkAiProviders();

        return [
            'laravel' => $laravelHealthy,
            'database' => $dbHealthy,
            'node' => $nodeHealthy,
            'ai' => $aiHealthy,
        ];
    }

    protected function buildAlerts(array $health, array $metrics, ?Device $selectedDevice = null): array
    {
        $alerts = [];

        if ($health['node']['status'] !== 'healthy' && $metrics['connected_devices'] > 0) {
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Node runtime perlu perhatian',
                'message' => 'Ada device yang terhubung, tetapi runtime WhatsApp Node tidak terdeteksi sehat. Auto reply bisa telat atau tidak terkirim.',
            ];
        }

        if ($selectedDevice && $selectedDevice->status !== 'Connected') {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Device terpilih belum connected',
                'message' => 'Pilih device yang sudah connected atau hubungkan ulang device ini sebelum tes menu, AI, atau auto reply.',
            ];
        }

        if ($health['ai']['status'] === 'critical') {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'AI bot aktif tetapi provider belum siap',
                'message' => 'Setidaknya satu API key OpenAI atau Gemini perlu valid agar rule AI bisa membalas tanpa fallback/silent mode.',
            ];
        }

        if ($metrics['paused_conversations'] > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Ada chat yang sedang dipause operator',
                'message' => $metrics['paused_conversations'] . ' percakapan sedang menunggu intervensi admin. Cek AI Conversations untuk resume bila perlu.',
            ];
        }

        return $alerts;
    }

    protected function buildSetupChecklist(?User $user, array $metrics, array $health): array
    {
        $profile = inferDeploymentProfileFromEnv();
        $items = [
            [
                'label' => 'Laravel Web',
                'status' => $health['laravel']['status'] === 'healthy' ? 'done' : 'pending',
                'message' => $health['laravel']['message'],
                'action' => 'Pastikan `.env`, `storage/`, dan `bootstrap/cache` writable.',
            ],
            [
                'label' => 'Database',
                'status' => $health['database']['status'] === 'healthy' ? 'done' : 'pending',
                'message' => $health['database']['message'],
                'action' => 'Siapkan database MySQL/MariaDB dan cek koneksi installer.',
            ],
            [
                'label' => 'Node Runtime',
                'status' => $health['node']['status'] === 'healthy' ? 'done' : 'pending',
                'message' => $health['node']['message'],
                'action' => 'Jalankan Node, cek port, lalu pastikan Socket.IO membalas `sid`.',
            ],
            [
                'label' => 'Device Added',
                'status' => $user && $user->devices()->exists() ? 'done' : 'pending',
                'message' => $user && $user->devices()->exists() ? 'Minimal satu device sudah dibuat.' : 'Belum ada device yang terdaftar.',
                'action' => 'Tambahkan device pertama dari dashboard untuk mulai scan QR/pairing code.',
            ],
            [
                'label' => 'Active Connection',
                'status' => $metrics['connected_devices'] > 0 ? 'done' : 'pending',
                'message' => $metrics['connected_devices'] > 0 ? 'Ada device yang sudah connected.' : 'Belum ada device connected.',
                'action' => 'Scan QR atau pairing code sampai status connected.',
            ],
            [
                'label' => 'API Key',
                'status' => $user && !empty($user->api_key) ? 'done' : 'pending',
                'message' => $user && !empty($user->api_key) ? 'API key user sudah tersedia.' : 'API key user masih kosong.',
                'action' => 'Gunakan API key untuk integrasi endpoint dan webhook.',
            ],
            [
                'label' => 'Deployment Profile',
                'status' => 'info',
                'message' => 'Preset aktif: ' . $profile,
                'action' => 'Buka Admin Settings jika perlu pindah mode instalasi.',
            ],
        ];

        return $items;
    }

    protected function checkLaravel(): array
    {
        $storageWritable = is_writable(storage_path());
        $credentialStorageWritable = canWritePath(getNodeCredentialsBasePath());
        $appKeyReady = !empty(config('app.key'));

        if ($storageWritable && $credentialStorageWritable && $appKeyReady) {
            return $this->statusCard('Laravel Web', 'healthy', 'Aplikasi web aktif, APP_KEY tersedia, storage dapat ditulis, dan session WhatsApp punya lokasi simpan yang writable.');
        }

        return $this->statusCard(
            'Laravel Web',
            'warning',
            'Ada konfigurasi inti yang belum ideal: ' . implode(', ', array_filter([
                $storageWritable ? null : 'storage tidak writable',
                $credentialStorageWritable ? null : 'storage session WhatsApp tidak writable',
                $appKeyReady ? null : 'APP_KEY kosong',
            ]))
        );
    }

    protected function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1 as ok');
            return $this->statusCard('MySQL', 'healthy', 'Koneksi database responsif.');
        } catch (\Throwable $th) {
            return $this->statusCard('MySQL', 'critical', 'Koneksi database gagal: ' . Str::limit($th->getMessage(), 140));
        }
    }

    protected function checkNodeRuntime(int $connectedDevices = 0): array
    {
        $baseUrl = getNodeRuntimeInternalUrl();
        if ($baseUrl === '') {
            return $this->statusCard('Node WA Runtime', 'critical', 'WA_URL_SERVER belum diatur.');
        }

        try {
            $response = Http::timeout(2)->get($baseUrl . '/socket.io/', [
                'EIO' => 4,
                'transport' => 'polling',
                't' => Str::random(6),
            ]);

            $body = (string) $response->body();
            if ($response->successful() && Str::contains($body, 'sid')) {
                return $this->statusCard('Node WA Runtime', 'healthy', 'Runtime Node merespons dan endpoint Socket.IO aktif.');
            }

            return $this->statusCard(
                'Node WA Runtime',
                $connectedDevices > 0 ? 'warning' : 'critical',
                'Runtime Node merespons tidak lengkap. Status HTTP: ' . $response->status()
            );
        } catch (\Throwable $th) {
            return $this->statusCard(
                'Node WA Runtime',
                $connectedDevices > 0 ? 'warning' : 'critical',
                'Gagal menjangkau runtime Node di ' . $baseUrl
            );
        }
    }

    protected function checkAiProviders(): array
    {
        $enabled = filter_var(env('AI_BOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $hasOpenAi = !empty(env('OPENAI_API_KEY'));
        $hasGemini = !empty(env('GEMINI_API_KEY'));

        if (!$enabled) {
            return $this->statusCard('AI Providers', 'disabled', 'AI bot dinonaktifkan secara global. Menu non-AI tetap aman digunakan.');
        }

        if (!$hasOpenAi && !$hasGemini) {
            return $this->statusCard('AI Providers', 'critical', 'AI bot aktif tetapi belum ada API key provider yang tersimpan.');
        }

        $providers = array_filter([
            $hasOpenAi ? 'OpenAI' : null,
            $hasGemini ? 'Gemini' : null,
        ]);

        return $this->statusCard('AI Providers', 'healthy', 'Provider siap dipakai: ' . implode(', ', $providers) . '.');
    }

    protected function deviceQuery(?User $user = null)
    {
        return Device::query()->when($user, function (Builder $query) use ($user) {
            $query->where('user_id', $user->id);
        });
    }

    protected function statusCard(string $label, string $status, string $message): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'badge_class' => match ($status) {
                'healthy' => 'bg-success',
                'warning' => 'bg-warning text-dark',
                'critical' => 'bg-danger',
                default => 'bg-secondary',
            },
        ];
    }
}
