<?php

namespace Modules\Genetics\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Genetics\Database\Factories\GeneticUploadFactory;
use Modules\Genetics\Enums\UploadStatus;

/**
 * One uploaded genetic file and its processing run. `error` is always a
 * UI-safe message, never provider text or file contents.
 *
 * @property int $id
 * @property int $user_id
 * @property string $original_name
 * @property string|null $path
 * @property UploadStatus $status
 * @property string|null $error
 * @property int $found_count
 * @property int $stored_count
 * @property int $queued_lookups
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 */
class GeneticUpload extends Model
{
    /** @use HasFactory<GeneticUploadFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'original_name', 'path', 'status', 'error',
        'found_count', 'stored_count', 'queued_lookups', 'started_at', 'completed_at',
    ];

    protected static function newFactory(): GeneticUploadFactory
    {
        return GeneticUploadFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => UploadStatus::class,
            'found_count' => 'integer',
            'stored_count' => 'integer',
            'queued_lookups' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
