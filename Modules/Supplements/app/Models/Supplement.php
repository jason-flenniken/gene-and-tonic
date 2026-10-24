<?php

namespace Modules\Supplements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Supplements\Database\Factories\SupplementFactory;

/**
 * @property int $id
 * @property string $name
 */
class Supplement extends Model
{
    /** @use HasFactory<SupplementFactory> */
    use HasFactory;

    protected $fillable = ['name', 'normalized_name', 'slug'];

    protected static function newFactory(): SupplementFactory
    {
        return SupplementFactory::new();
    }

    /** @return HasOne<SupplementResearch, $this> */
    public function research(): HasOne
    {
        return $this->hasOne(SupplementResearch::class);
    }

    /** @return HasMany<SupplementGeneInteraction, $this> */
    public function geneInteractions(): HasMany
    {
        return $this->hasMany(SupplementGeneInteraction::class);
    }

    /** @return HasManyThrough<SupplementEffect, SupplementResearch, $this> */
    public function effects(): HasManyThrough
    {
        return $this->hasManyThrough(SupplementEffect::class, SupplementResearch::class, 'supplement_id', 'research_id');
    }

    /** @return HasManyThrough<SupplementStudy, SupplementResearch, $this> */
    public function studies(): HasManyThrough
    {
        return $this->hasManyThrough(SupplementStudy::class, SupplementResearch::class, 'supplement_id', 'research_id');
    }
}
