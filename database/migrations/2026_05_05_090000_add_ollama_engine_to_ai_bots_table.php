<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddOllamaEngineToAiBotsTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE ai_bots MODIFY COLUMN engine_type ENUM('openai', 'gemini', 'ollama', 'webhook') NOT NULL DEFAULT 'openai'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE ai_bots MODIFY COLUMN engine_type ENUM('openai', 'gemini', 'webhook') NOT NULL DEFAULT 'openai'");
    }
}
