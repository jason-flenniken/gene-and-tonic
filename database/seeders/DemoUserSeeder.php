<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Create or refresh the demo user from config('auth.demo').
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('auth.demo.email')],
            [
                'name' => config('auth.demo.name'),
                'password' => config('auth.demo.password'),
            ],
        );
    }
}
