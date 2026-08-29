<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('whatsapp_message_id');
            $table->string('phone_number', 32)->nullable();
            $table->string('resolved_jid');
            $table->string('delivery_status', 24)->default('pending');
            $table->unsignedTinyInteger('delivery_rank')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('server_ack_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['device_id', 'whatsapp_message_id'],
                'wa_outbound_device_message_unique'
            );
            $table->index(['device_id', 'phone_number'], 'wa_outbound_device_phone_index');
        });

        Schema::table('message_histories', function (Blueprint $table) {
            $table->string('whatsapp_message_id')->nullable()->after('status');
            $table->string('resolved_jid')->nullable()->after('whatsapp_message_id');
            $table->string('delivery_status', 24)->nullable()->after('resolved_jid');
            $table->timestamp('sent_at')->nullable()->after('delivery_status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->index(
                ['device_id', 'whatsapp_message_id'],
                'message_histories_device_wa_message_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('message_histories', function (Blueprint $table) {
            $table->dropIndex('message_histories_device_wa_message_index');
            $table->dropColumn([
                'whatsapp_message_id',
                'resolved_jid',
                'delivery_status',
                'sent_at',
                'delivered_at',
                'read_at',
            ]);
        });

        Schema::dropIfExists('whatsapp_outbound_messages');
    }
};
