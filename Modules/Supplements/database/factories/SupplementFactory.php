<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Supplements\Models\Supplement;

/** @extends Factory<Supplement> */
class SupplementFactory extends Factory
{
    protected $model = Supplement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' '.fake()->word();

        return [
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'slug' => Str::slug($name),
        ];
    }
}
