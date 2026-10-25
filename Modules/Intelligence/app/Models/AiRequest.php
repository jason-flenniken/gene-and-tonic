<?php

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Database\Factories\AiRequestFactory;

/**
 * Private to Intelligence. One row per provider call; never holds prompt or response bodies.
 */
class AiRequest extends Model
{
    /** @use HasFactory<AiRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'operation',
        'profile',
        'provider',
        'model',
        'effort',
        'input_tokens',
        'output_tokens',
        'credits',
        'latency_ms',
        'estimated_cost_usd',
        'status',
        'error',
    ];

    protected static function newFactory(): AiRequestFactory
    {
        return AiRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'credits' => 'integer',
            'latency_ms' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
        ];
    }
}
