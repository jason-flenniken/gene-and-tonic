<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Supplements\Models\SupplementSource;

/** @extends Factory<SupplementSource> */
class SupplementSourceFactory extends Factory
{
    protected $model = SupplementSource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'research_id' => SupplementResearchFactory::new(),
            'source_key' => 'S'.fake()->unique()->numberBetween(1, 9999),
            'url' => fake()->url(),
            'title' => fake()->sentence(4),
            'domain' => 'pubmed.ncbi.nlm.nih.gov',
        ];
    }
}
