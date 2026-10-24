<?php

namespace Modules\Supplements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplements\Database\Factories\SupplementStudyFactory;
use Modules\Supplements\Enums\EvidenceGrade;

class SupplementStudy extends Model
{
    /** @use HasFactory<SupplementStudyFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['research_id', 'title', 'design', 'population', 'sample_size', 'year', 'finding', 'evidence_grade', 'source_key', 'pmid'];

    protected static function newFactory(): SupplementStudyFactory
    {
        return SupplementStudyFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['evidence_grade' => EvidenceGrade::class, 'sample_size' => 'integer', 'year' => 'integer'];
    }

    /** @return BelongsTo<SupplementResearch, $this> */
    public function research(): BelongsTo
    {
        return $this->belongsTo(SupplementResearch::class, 'research_id');
    }
}
