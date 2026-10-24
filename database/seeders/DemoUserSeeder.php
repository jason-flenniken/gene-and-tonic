<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Create or refresh the demo user from config('auth.demo').
     *
     * Fails closed: outside local and testing it refuses to run unless DEMO_USER_PASSWORD is
     * explicitly set to something other than the well-known default, so a deploy cannot ship a
     * guessable credential that guards genetic data.
     */
    public function run(): void
    {
        $password = config('auth.demo.password');

        if (! app()->environment(['local', 'testing']) && (! is_string($password) || trim($password) === '' || $password === 'password')) {
            $this->command?->error('DemoUserSeeder skipped: set DEMO_USER_PASSWORD to a strong, non-default value to seed the demo user outside local/testing.');

            return;
        }

        User::updateOrCreate(
            ['email' => config('auth.demo.email')],
            [
                'name' => config('auth.demo.name'),
                'password' => $password,
            ],
        );
    }
}
