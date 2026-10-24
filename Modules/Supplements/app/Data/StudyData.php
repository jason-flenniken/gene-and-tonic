<?php

namespace Modules\Supplements\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\EvidenceGrade;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class StudyData implements Arrayable
{
    use ArrayableData;

    public function __construct(
        public string $title,
        public ?string $design,
        public ?string $population,
        public ?int $sampleSize,
        public ?int $year,
        public ?string $finding,
        public ?EvidenceGrade $evidenceGrade,
        public ?string $sourceKey,
        public ?string $pmid,
    ) {}

    public function pubmedUrl(): ?string
    {
        return $this->pmid !== null && preg_match('/^\d{1,9}$/', $this->pmid) === 1
            ? 'https://pubmed.ncbi.nlm.nih.gov/'.$this->pmid.'/'
            : null;
    }
}
