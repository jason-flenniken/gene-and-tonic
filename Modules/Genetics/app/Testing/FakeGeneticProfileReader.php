<?php

namespace Modules\Genetics\Testing;

use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\GeneticProfileData;

/**
 * In-memory GeneticProfileReader for other modules' tests.
 */
class FakeGeneticProfileReader implements GeneticProfileReader
{
    /** @var array<int, GeneticProfileData> */
    private array $profiles = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(GeneticProfileReader::class, $fake);

        return $fake;
    }

    public function for(int $userId, GeneticProfileData $profile): self
    {
        $this->profiles[$userId] = $profile;

        return $this;
    }

    public function forUser(int $userId): GeneticProfileData
    {
        return $this->profiles[$userId] ?? new GeneticProfileData($userId, []);
    }
}
