<?php

namespace Modules\Supplements\Testing;

use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementResearchData;

/**
 * In-memory SupplementResearchReader for other modules' tests.
 */
class FakeSupplementResearchReader implements SupplementResearchReader
{
    /** @var array<int, SupplementResearchData> */
    private array $research = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(SupplementResearchReader::class, $fake);

        return $fake;
    }

    public function with(SupplementResearchData $research): self
    {
        $this->research[$research->supplementId] = $research;

        return $this;
    }

    public function forSupplement(int $supplementId): ?SupplementResearchData
    {
        return $this->research[$supplementId] ?? null;
    }
}
