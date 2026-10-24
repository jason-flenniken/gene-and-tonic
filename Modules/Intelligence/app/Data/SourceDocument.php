<?php

namespace Modules\Intelligence\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * Untrusted scraped content: render only through escaped output.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SourceDocument implements Arrayable
{
    use ArrayableData;

    public function __construct(
        public string $id,
        public string $url,
        public string $title,
        public string $domain,
        public ?string $description,
        public string $markdown,
    ) {}
}
