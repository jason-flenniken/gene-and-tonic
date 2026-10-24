<?php

namespace Modules\Intelligence\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SearchQuery implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<string>  $domains  Restrict results to these domains (and their subdomains).
     */
    public function __construct(
        public string $query,
        public array $domains = [],
        public int $limit = 8,
        public bool $scrape = true,
    ) {}
}
