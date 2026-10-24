<?php

namespace Modules\Supplements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplements\Database\Factories\SupplementEffectFactory;
use Modules\Supplements\Enums\EvidenceGrade;

class SupplementEffect extends Model
{
    /** @use HasFactory<SupplementEffectFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['research_id', 'claim', 'outcome', 'evidence_grade', 'source_keys'];

    protected static function newFactory(): SupplementEffectFactory
    {
        return SupplementEffectFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['evidence_grade' => EvidenceGrade::class, 'source_keys' => 'array'];
    }

    /** @return BelongsTo<SupplementResearch, $this> */
    public function research(): BelongsTo
    {
        return $this->belongsTo(SupplementResearch::class, 'research_id');
    }
}
