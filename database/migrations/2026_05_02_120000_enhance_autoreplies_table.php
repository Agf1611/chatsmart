<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnhanceAutorepliesTable extends Migration
{
    public function up()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->string('name')->nullable()->after('device_id');
            $table->json('reply_config')->nullable()->after('reply');
            $table->unsignedInteger('priority')->default(100)->after('status');
            $table->enum('schedule_mode', ['always', 'scheduled'])->default('always')->after('priority');
            $table->json('schedule_days')->nullable()->after('schedule_mode');
            $table->time('schedule_start')->nullable()->after('schedule_days');
            $table->time('schedule_end')->nullable()->after('schedule_start');
        });

        DB::table('autoreplies')
            ->orderBy('id')
            ->chunkById(100, function ($rules) {
                foreach ($rules as $rule) {
                    DB::table('autoreplies')
                        ->where('id', $rule->id)
                        ->update([
                            'name' => $rule->name ?: $rule->keyword,
                            'reply_config' => json_encode($this->buildReplyConfig($rule->type, $rule->reply)),
                            'priority' => $rule->priority ?: 100,
                            'schedule_mode' => $rule->schedule_mode ?: 'always',
                        ]);
                }
            });
    }

    public function down()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'reply_config',
                'priority',
                'schedule_mode',
                'schedule_days',
                'schedule_start',
                'schedule_end',
            ]);
        });
    }

    protected function buildReplyConfig($type, $replyJson)
    {
        $payload = json_decode($replyJson, true);

        if (!is_array($payload)) {
            return [];
        }

        switch ($type) {
            case 'text':
                return [
                    'message' => isset($payload['text']) ? $payload['text'] : '',
                ];
            case 'media':
                return [
                    'url' => isset($payload['url']) ? $payload['url'] : '',
                    'media_type' => isset($payload['type']) ? $payload['type'] : 'document',
                    'caption' => isset($payload['caption']) ? $payload['caption'] : '',
                ];
            case 'list':
                $items = [];
                if (!empty($payload['sections'][0]['rows']) && is_array($payload['sections'][0]['rows'])) {
                    foreach ($payload['sections'][0]['rows'] as $row) {
                        $items[] = isset($row['title']) ? $row['title'] : '';
                    }
                }

                return [
                    'message' => isset($payload['text']) ? $payload['text'] : '',
                    'buttontext' => isset($payload['buttonText']) ? $payload['buttonText'] : '',
                    'name' => isset($payload['title']) ? $payload['title'] : '',
                    'title' => isset($payload['sections'][0]['title']) ? $payload['sections'][0]['title'] : '',
                    'items' => $items,
                ];
            case 'button':
                $buttons = [];
                if (!empty($payload['buttons']) && is_array($payload['buttons'])) {
                    foreach ($payload['buttons'] as $button) {
                        $buttons[] = isset($button['buttonText']['displayText']) ? $button['buttonText']['displayText'] : '';
                    }
                }

                return [
                    'message' => isset($payload['text']) ? $payload['text'] : (isset($payload['caption']) ? $payload['caption'] : ''),
                    'footer' => isset($payload['footer']) ? $payload['footer'] : '',
                    'image' => isset($payload['image']['url']) ? $payload['image']['url'] : '',
                    'buttons' => $buttons,
                ];
            case 'template':
                $templates = [];
                if (!empty($payload['templateButtons']) && is_array($payload['templateButtons'])) {
                    foreach ($payload['templateButtons'] as $template) {
                        if (isset($template['urlButton'])) {
                            $templates[] = 'url|' . $template['urlButton']['displayText'] . '|' . $template['urlButton']['url'];
                            continue;
                        }

                        if (isset($template['callButton'])) {
                            $templates[] = 'call|' . $template['callButton']['displayText'] . '|' . $template['callButton']['phoneNumber'];
                        }
                    }
                }

                return [
                    'message' => isset($payload['text']) ? $payload['text'] : (isset($payload['caption']) ? $payload['caption'] : ''),
                    'footer' => isset($payload['footer']) ? $payload['footer'] : '',
                    'image' => isset($payload['image']['url']) ? $payload['image']['url'] : '',
                    'templates' => $templates,
                ];
            default:
                return [];
        }
    }
}
