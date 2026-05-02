<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $username = env('SEED_ADMIN_USERNAME', 'admin');
        $email = env('SEED_ADMIN_EMAIL', 'admin@example.invalid');
        $password = env('SEED_ADMIN_PASSWORD');

        User::updateOrCreate(
            ['username' => $username],
            [
                'email' => $email,
                'email_verified_at' => now(),
                'limit_device' => 100,
                'active_subscription' => 'lifetime',
                'password' => Hash::make($password ?: Str::random(32)),
                'api_key' => Str::random(15),
                'chunk_blast' => 100,
            ]
        );
    }
}
