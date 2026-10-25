<?php

namespace Modules\Supplements\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Supplements\Database\Factories\SupplementResearchFactory;
use Modules\Supplements\Enums\ResearchStatus;

/**
 * @property int $id
 * @property int $supplement_id
 * @property ResearchStatus $status
 * @property string|null $error
 * @property string|null $summary
 * @property string|null $safety_notes
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $genetics_checked_at
 * @property string|null $kicker
 * @property array<int, mixed>|null $topics
 */
class SupplementResearch extends Model
{
    /** @use HasFactory<SupplementResearchFactory> */
    use HasFactory;

    protected $table = 'supplement_researches';

    protected $fillable = [
        'supplement_id', 'status', 'error', 'summary', 'safety_notes',
        'queued_at', 'started_at', 'completed_at', 'genetics_checked_at', 'kicker', 'topics',
    ];

    protected static function newFactory(): SupplementResearchFactory
    {
        return SupplementResearchFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ResearchStatus::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'genetics_checked_at' => 'datetime',
            'topics' => 'array',
        ];
    }

    /** @return BelongsTo<Supplement, $this> */
    public function supplement(): BelongsTo
    {
        return $this->belongsTo(Supplement::class);
    }

    /** @return HasMany<SupplementSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(SupplementSource::class, 'research_id');
    }

    /** @return HasMany<SupplementEffect, $this> */
    public function effects(): HasMany
    {
        return $this->hasMany(SupplementEffect::class, 'research_id');
    }

    /** @return HasMany<SupplementMechanism, $this> */
    public function mechanisms(): HasMany
    {
        return $this->hasMany(SupplementMechanism::class, 'research_id');
    }

    /** @return HasMany<SupplementStudy, $this> */
    public function studies(): HasMany
    {
        return $this->hasMany(SupplementStudy::class, 'research_id');
    }
}
