<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_recipient_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('phone_number', 32);
            $table->string('pn_jid');
            $table->string('lid_jid');
            $table->string('source', 32)->default('runtime');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'phone_number'], 'wa_recipient_mapping_device_phone_unique');
            $table->index(['device_id', 'lid_jid'], 'wa_recipient_mapping_device_lid_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_recipient_mappings');
    }
};
