<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_tag_id')->nullable()->after('device_id');
            $table->foreign('contact_tag_id')->references('id')->on('tags')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('autoreplies', function (Blueprint $table) {
            $table->dropForeign(['contact_tag_id']);
            $table->dropColumn('contact_tag_id');
        });
    }
};
