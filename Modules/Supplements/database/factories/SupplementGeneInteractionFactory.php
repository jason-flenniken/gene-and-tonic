<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Models\SupplementGeneInteraction;

/** @extends Factory<SupplementGeneInteraction> */
class SupplementGeneInteractionFactory extends Factory
{
    protected $model = SupplementGeneInteraction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'supplement_id' => SupplementFactory::new(),
            'gene' => 'MTHFR',
            'rsid' => 'rs1801133',
            'allele' => 'T',
            'relation' => GeneRelation::Helps,
            'explanation' => fake()->sentence(),
            'source_keys' => ['S1'],
            'position' => 0,
        ];
    }
}
