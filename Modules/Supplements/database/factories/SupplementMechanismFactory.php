<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Supplements\Models\SupplementMechanism;

/** @extends Factory<SupplementMechanism> */
class SupplementMechanismFactory extends Factory
{
    protected $model = SupplementMechanism::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'research_id' => SupplementResearchFactory::new(),
            'description' => fake()->sentence(),
            'source_keys' => ['S1'],
        ];
    }
}
