<?php

namespace Modules\Genetics\Console;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\GeneVariant;

/**
 * Fails uploads and reference lookups that have been active longer than
 * genetics.reap_after_minutes, so a crashed worker cannot leave a run spinning
 * forever. A failed upload also loses its raw file.
 */
class ReapStaleCommand extends Command
{
    public const TIMED_OUT_ERROR = 'Timed out, try again.';

    /** @var string */
    protected $signature = 'genetics:reap-stale';

    /** @var string */
    protected $description = 'Fail genetic uploads and reference lookups that have been active too long';

    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) config('genetics.reap_after_minutes', 15));

        $uploadCount = $this->reapUploads($cutoff);
        $lookupCount = GeneVariant::query()
            ->where('status', LookupStatus::LookingUp)
            ->where('updated_at', '<', $cutoff)
            ->update(['status' => LookupStatus::Failed, 'error' => self::TIMED_OUT_ERROR]);

        $this->info("Failed {$uploadCount} stale upload(s) and {$lookupCount} stale lookup(s).");

        return self::SUCCESS;
    }

    private function reapUploads(DateTimeInterface $cutoff): int
    {
        $reaped = 0;

        GeneticUpload::query()
            ->whereIn('status', [UploadStatus::Queued, UploadStatus::Parsing, UploadStatus::LookingUp])
            ->where('updated_at', '<', $cutoff)
            ->chunkById(100, function ($uploads) use (&$reaped): void {
                foreach ($uploads as $upload) {
                    if ($upload->path !== null) {
                        Storage::disk('genetics')->delete($upload->path);
                    }

                    $upload->update([
                        'status' => UploadStatus::Failed,
                        'error' => self::TIMED_OUT_ERROR,
                        'path' => null,
                    ]);

                    $reaped++;
                }
            });

        return $reaped;
    }
}
