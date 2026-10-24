<?php

namespace Modules\Genetics\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Genetics\Models\UserVariant;

/** @extends Factory<UserVariant> */
class UserVariantFactory extends Factory
{
    protected $model = UserVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rsid' => 'rs'.fake()->unique()->numberBetween(1000, 99999999),
            'genotype' => 'AG',
            'source' => 'manual',
            'genetic_upload_id' => null,
        ];
    }
}
