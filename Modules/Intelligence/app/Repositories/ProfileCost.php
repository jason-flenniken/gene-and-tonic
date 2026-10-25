<?php

namespace Modules\Intelligence\Repositories;

/**
 * Internal to Intelligence: usage aggregated per profile and operation.
 */
final readonly class ProfileCost
{
    public function __construct(
        public ?string $profile,
        public string $operation,
        public int $calls,
        public int $failed,
        public int $credits,
        public float $cost,
    ) {}
}
