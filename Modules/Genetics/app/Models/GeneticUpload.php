<?php

namespace Modules\Genetics\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Genetics\Database\Factories\GeneticUploadFactory;
use Modules\Genetics\Enums\ParseMethod;
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
 * @property ParseMethod|null $parse_method
 * @property int $local_count
 * @property int $ai_count
 * @property int $skipped_count
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
        'parse_method', 'local_count', 'ai_count', 'skipped_count',
        'found_count', 'stored_count', 'queued_lookups', 'started_at', 'completed_at',
    ];

    protected static function newFactory(): GeneticUploadFactory
    {
        return GeneticUploadFactory::new();
    }

    /**
     * How the rows were read, in plain words, e.g. "70 read, 4 via AI, 9 skipped".
     * Null for uploads processed before this was recorded.
     */
    public function readSummary(): ?string
    {
        if ($this->parse_method === null) {
            return null;
        }

        $parts = [];

        if ($this->parse_method !== ParseMethod::Ai) {
            $parts[] = number_format($this->local_count).' read';
        }

        if ($this->parse_method !== ParseMethod::Local) {
            $parts[] = number_format($this->ai_count).' via AI';
        }

        if ($this->skipped_count > 0) {
            $parts[] = number_format($this->skipped_count).' skipped';
        }

        return implode(', ', $parts);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => UploadStatus::class,
            'parse_method' => ParseMethod::class,
            'local_count' => 'integer',
            'ai_count' => 'integer',
            'skipped_count' => 'integer',
            'found_count' => 'integer',
            'stored_count' => 'integer',
            'queued_lookups' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
