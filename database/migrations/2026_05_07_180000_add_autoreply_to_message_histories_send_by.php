<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE message_histories MODIFY COLUMN send_by ENUM('api','web','autoreply') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE message_histories MODIFY COLUMN send_by ENUM('api','web') NOT NULL");
    }
};
