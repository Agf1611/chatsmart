<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDemoDeviceColumnsToDevicesTable extends Migration
{
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'webhook_read')) {
                $table->boolean('webhook_read')->default(false)->after('webhook');
            }

            if (!Schema::hasColumn('devices', 'webhook_reject_call')) {
                $table->boolean('webhook_reject_call')->default(false)->after('webhook_read');
            }

            if (!Schema::hasColumn('devices', 'set_available')) {
                $table->boolean('set_available')->default(false)->after('webhook_reject_call');
            }

            if (!Schema::hasColumn('devices', 'webhook_typing')) {
                $table->boolean('webhook_typing')->default(false)->after('set_available');
            }
        });
    }

    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $columns = ['webhook_read', 'webhook_reject_call', 'set_available', 'webhook_typing'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
