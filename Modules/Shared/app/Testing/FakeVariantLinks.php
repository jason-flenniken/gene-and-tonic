<?php

namespace Modules\Shared\Testing;

use Modules\Shared\Contracts\VariantLinks;
use Modules\Shared\Data\LinkedSupplementData;

/**
 * In-memory VariantLinks for other modules' tests.
 */
class FakeVariantLinks implements VariantLinks
{
    /** @var array<int, array<string, list<LinkedSupplementData>>> */
    private array $links = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(VariantLinks::class, $fake);

        return $fake;
    }

    /**
     * @param  array<string, list<LinkedSupplementData>>  $map
     */
    public function for(int $userId, array $map): self
    {
        $this->links[$userId] = $map;

        return $this;
    }

    public function forUser(int $userId): array
    {
        return $this->links[$userId] ?? [];
    }
}
