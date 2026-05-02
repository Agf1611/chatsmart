<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class NumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $devices = array_filter(array_map('trim', explode(',', env('SEED_DEFAULT_DEVICES', ''))));

        foreach ($devices as $deviceBody) {
            Device::firstOrCreate(
                ['body' => $deviceBody],
                [
                    'user_id' => 1,
                    'webhook' => '',
                    'status' => 'Disconnect',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
