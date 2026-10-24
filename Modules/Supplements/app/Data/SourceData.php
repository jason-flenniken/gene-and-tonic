<?php

namespace Modules\Supplements\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SourceData implements Arrayable
{
    use ArrayableData;

    public function __construct(
        public string $key,
        public ?string $url,
        public string $title,
        public ?string $domain,
    ) {}

    /** Only http(s) URLs may be rendered as links. */
    public function safeUrl(): ?string
    {
        return $this->url !== null && preg_match('#^https?://#i', $this->url) === 1
            ? $this->url
            : null;
    }
}
