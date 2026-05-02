<?php

namespace Tests\Unit;

use App\Services\AiReplyService;
use App\Services\AutoreplyRuleService;
use App\Services\MessageService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AutoreplyRuleServiceTest extends TestCase
{
    protected function makeService(): AutoreplyRuleService
    {
        $messageService = new class implements MessageService {
            public function formatText($text): array
            {
                return ['text' => $text];
            }

            public function formatImage($image, $caption = ''): array
            {
                return ['image' => ['url' => $image], 'caption' => $caption];
            }

            public function formatButtons($text, $buttons, $urlimage = '', $footer = ''): array
            {
                return ['text' => $text, 'buttons' => $buttons, 'footer' => $footer];
            }

            public function formatTemplates($text, $buttons, $urlimage = '', $footer = ''): array
            {
                return ['text' => $text, 'templateButtons' => $buttons, 'footer' => $footer];
            }

            public function formatLists($text, $lists, $title, $name, $buttonText, $footer = ''): array
            {
                return ['text' => $text, 'sections' => $lists, 'title' => $title, 'buttonText' => $buttonText];
            }

            public function format($type, $data): array
            {
                if ($type === 'text') {
                    return ['text' => $data->message];
                }

                return (array) $data;
            }
        };

        return new AutoreplyRuleService($messageService, new AiReplyService());
    }

    public function test_first_chat_rule_wins_before_keyword_rules()
    {
        $service = $this->makeService();
        $rules = Collection::make([
            [
                'id' => 1,
                'name' => 'Welcome',
                'trigger_event' => 'first_chat',
                'keyword' => '__first_chat__:1',
                'type_keyword' => 'Equal',
                'reply_when' => 'All',
                'type' => 'text',
                'reply' => ['text' => 'Halo'],
                'status' => 'active',
                'priority' => 1,
                'schedule_mode' => 'always',
                'updated_at' => now()->subMinute()->toDateTimeString(),
            ],
            [
                'id' => 2,
                'name' => 'Menu',
                'trigger_event' => 'keyword',
                'keyword' => 'menu',
                'type_keyword' => 'Equal',
                'reply_when' => 'All',
                'type' => 'text',
                'reply' => ['text' => 'Daftar menu'],
                'status' => 'active',
                'priority' => 100,
                'schedule_mode' => 'always',
                'updated_at' => now()->toDateTimeString(),
            ],
        ]);

        $match = $service->matchRules($rules, 'menu', 'personal', Carbon::now(), [
            'is_first_chat' => true,
            'registered_tag_ids' => [],
        ]);

        $this->assertTrue($match['matched']);
        $this->assertSame(1, $match['rule']['id']);
        $this->assertSame('first_chat', $match['rule']['trigger_event']);
    }

    public function test_contain_rule_uses_lowest_priority_and_whitelist()
    {
        $service = $this->makeService();
        $rules = Collection::make([
            [
                'id' => 10,
                'name' => 'Tagihan Prioritas Rendah',
                'trigger_event' => 'keyword',
                'keyword' => 'tagihan',
                'type_keyword' => 'Contain',
                'reply_when' => 'All',
                'contact_tag_id' => 99,
                'type' => 'text',
                'reply' => ['text' => 'Rule salah whitelist'],
                'status' => 'active',
                'priority' => 1,
                'schedule_mode' => 'always',
                'updated_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 11,
                'name' => 'Tagihan Aktif',
                'trigger_event' => 'keyword',
                'keyword' => 'tagihan',
                'type_keyword' => 'Contain',
                'reply_when' => 'All',
                'contact_tag_id' => 5,
                'type' => 'text',
                'reply' => ['text' => 'Rule benar'],
                'status' => 'active',
                'priority' => 5,
                'schedule_mode' => 'always',
                'updated_at' => now()->subMinute()->toDateTimeString(),
            ],
        ]);

        $match = $service->matchRules($rules, 'cek tagihan saya', 'personal', Carbon::now(), [
            'registered_tag_ids' => [5],
        ]);

        $this->assertTrue($match['matched']);
        $this->assertSame(11, $match['rule']['id']);
    }

    public function test_format_for_persistence_keeps_transport_policy()
    {
        $service = $this->makeService();

        $payload = $service->formatForPersistence([
            'name' => 'Welcome',
            'device_id' => 1,
            'contact_tag_id' => null,
            'transport_policy' => 'interactive_preferred',
            'trigger_event' => 'keyword',
            'keyword' => 'menu',
            'type_keyword' => 'Equal',
            'reply_when' => 'All',
            'type' => 'text',
            'message' => 'Halo',
            'status' => 'active',
            'is_quoted' => false,
            'priority' => 100,
            'schedule_mode' => 'always',
        ]);

        $this->assertSame('interactive_preferred', $payload['transport_policy']);
    }
}
