<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFirstChatSupportToAutoreplies extends Migration
{
    public function up()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->enum('trigger_event', ['keyword', 'first_chat'])->default('keyword')->after('name');
        });

        DB::table('autoreplies')->whereNull('trigger_event')->update([
            'trigger_event' => 'keyword',
        ]);

        Schema::create('incoming_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->string('chat_jid');
            $table->unsignedInteger('message_count')->default(1);
            $table->timestamp('first_received_at')->useCurrent();
            $table->timestamp('last_received_at')->useCurrent();
            $table->timestamps();

            $table->unique(['device_id', 'chat_jid']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('incoming_message_logs');

        Schema::table('autoreplies', function (Blueprint $table) {
            $table->dropColumn('trigger_event');
        });
    }
}
