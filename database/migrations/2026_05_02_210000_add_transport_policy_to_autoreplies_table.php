<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->string('transport_policy', 50)
                ->default('text_fallback')
                ->after('contact_tag_id');
        });

        DB::table('autoreplies')
            ->whereIn('type', ['list', 'button', 'template'])
            ->update([
                'transport_policy' => DB::raw("CASE WHEN type = 'list' THEN 'interactive_preferred' ELSE 'text_fallback' END"),
            ]);
    }

    public function down()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->dropColumn('transport_policy');
        });
    }
};
