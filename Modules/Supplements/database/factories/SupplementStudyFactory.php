<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Models\SupplementStudy;

/** @extends Factory<SupplementStudy> */
class SupplementStudyFactory extends Factory
{
    protected $model = SupplementStudy::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'research_id' => SupplementResearchFactory::new(),
            'title' => fake()->sentence(5),
            'design' => 'RCT',
            'population' => 'Adults',
            'sample_size' => 60,
            'year' => 2020,
            'finding' => fake()->sentence(),
            'evidence_grade' => EvidenceGrade::Moderate,
            'source_key' => 'S1',
            'pmid' => null,
        ];
    }
}
