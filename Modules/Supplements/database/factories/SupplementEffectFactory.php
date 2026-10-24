<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Models\SupplementEffect;

/** @extends Factory<SupplementEffect> */
class SupplementEffectFactory extends Factory
{
    protected $model = SupplementEffect::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'research_id' => SupplementResearchFactory::new(),
            'claim' => fake()->sentence(),
            'outcome' => 'improved sleep',
            'evidence_grade' => EvidenceGrade::Moderate,
            'source_keys' => ['S1'],
        ];
    }
}
