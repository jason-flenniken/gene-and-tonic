<?php

namespace Modules\Supplements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplements\Database\Factories\SupplementSourceFactory;

/**
 * @property string $source_key
 * @property string|null $url
 * @property string $title
 * @property string|null $domain
 */
class SupplementSource extends Model
{
    /** @use HasFactory<SupplementSourceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['research_id', 'source_key', 'url', 'title', 'domain'];

    protected static function newFactory(): SupplementSourceFactory
    {
        return SupplementSourceFactory::new();
    }

    /** @return BelongsTo<SupplementResearch, $this> */
    public function research(): BelongsTo
    {
        return $this->belongsTo(SupplementResearch::class, 'research_id');
    }
}
