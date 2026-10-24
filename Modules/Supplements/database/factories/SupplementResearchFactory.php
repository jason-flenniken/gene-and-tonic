<?php

namespace Modules\Supplements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\SupplementResearch;

/** @extends Factory<SupplementResearch> */
class SupplementResearchFactory extends Factory
{
    protected $model = SupplementResearch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'supplement_id' => SupplementFactory::new(),
            'status' => ResearchStatus::Queued,
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
