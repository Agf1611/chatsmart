<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAiBotSupport extends Migration
{
    public function up()
    {
        Schema::create('ai_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('device_id');
            $table->string('name');
            $table->enum('engine_type', ['openai', 'gemini', 'webhook'])->default('openai');
            $table->string('model')->nullable();
            $table->enum('thinking_mode', ['precise', 'balanced', 'creative'])->default('balanced');
            $table->text('system_prompt')->nullable();
            $table->string('persona')->nullable();
            $table->string('response_style')->nullable();
            $table->unsignedSmallInteger('memory_window')->default(20);
            $table->enum('handoff_mode', ['manual_pause', 'none'])->default('manual_pause');
            $table->enum('fallback_mode', ['silent', 'text'])->default('silent');
            $table->text('fallback_message')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(20);
            $table->unsignedInteger('max_output_tokens')->default(400);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_auth_header')->nullable();
            $table->text('webhook_auth_token')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->onDelete('cascade');
            $table->unsignedBigInteger('device_id');
            $table->string('chat_jid');
            $table->string('contact_name')->nullable();
            $table->enum('context_type', ['personal', 'group'])->default('personal');
            $table->enum('status', ['active', 'paused'])->default('active');
            $table->timestamp('paused_until')->nullable();
            $table->string('paused_reason')->nullable();
            $table->text('short_summary')->nullable();
            $table->text('last_user_message')->nullable();
            $table->text('last_bot_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_bot_reply_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_bot_id', 'device_id', 'chat_jid'], 'ai_conversations_unique_chat');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });

        Schema::create('ai_conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->onDelete('cascade');
            $table->enum('role', ['system', 'user', 'assistant', 'event']);
            $table->longText('content');
            $table->string('provider')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            $table->unsignedInteger('token_count')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('autoreplies', function (Blueprint $table) {
            $table->unsignedBigInteger('ai_bot_id')->nullable()->after('device_id');
            $table->foreign('ai_bot_id')->references('id')->on('ai_bots')->onDelete('set null');
        });

        DB::statement("ALTER TABLE autoreplies MODIFY COLUMN type ENUM('text','image','button','template','list','media','ai') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE autoreplies MODIFY COLUMN type ENUM('text','image','button','template','list','media') NOT NULL");

        Schema::table('autoreplies', function (Blueprint $table) {
            $table->dropForeign(['ai_bot_id']);
            $table->dropColumn('ai_bot_id');
        });

        Schema::dropIfExists('ai_conversation_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_bots');
    }
}
