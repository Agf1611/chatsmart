<?php

namespace App\Console\Commands;

use App\Models\MessageHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupMessageHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:cleanup-history {--days=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old message histories based on retention settings.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $forced = (bool) $this->option('force');
        $enabled = filter_var(env('MESSAGE_HISTORY_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN);

        if (!$forced && !$enabled) {
            $this->info('Auto cleanup message history is disabled.');
            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: env('MESSAGE_HISTORY_RETENTION_DAYS', 30));
        if ($days < 1) {
            $days = 30;
        }

        $cutoffDate = now()->subDays($days);
        $query = MessageHistory::query()->where('created_at', '<', $cutoffDate);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("No message history older than {$days} days.");
            return self::SUCCESS;
        }

        $query->delete();

        $message = "Deleted {$count} message histories older than {$days} days.";
        $this->info($message);
        Log::info($message);

        return self::SUCCESS;
    }
}
