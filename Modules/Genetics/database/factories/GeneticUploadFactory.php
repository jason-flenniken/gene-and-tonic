<?php

namespace Modules\Genetics\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Models\GeneticUpload;

/** @extends Factory<GeneticUpload> */
class GeneticUploadFactory extends Factory
{
    protected $model = GeneticUpload::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => 'genome.txt',
            'path' => 'uploads/'.Str::uuid().'.txt',
            'status' => UploadStatus::Queued,
        ];
    }

    public function complete(): static
    {
        return $this->state(['status' => UploadStatus::Complete, 'path' => null]);
    }
}
