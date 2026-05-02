<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationalRoutesTest extends TestCase
{
    public function test_guest_is_redirected_from_home()
    {
        $response = $this->get('/home');

        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_operational_audit()
    {
        $response = $this->get('/admin/operational-audit');

        $response->assertRedirect('/login');
    }

    public function test_internal_ai_endpoint_requires_internal_token()
    {
        $response = $this->postJson('/api/internal/ai/respond', [
            'device_body' => '62857',
            'chat_jid' => '62857@s.whatsapp.net',
            'matched_rule_id' => 1,
            'bot_id' => 1,
            'context_type' => 'personal',
        ]);

        $response->assertStatus(403);
    }
}
