<?php

namespace Modules\Genetics\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Actions\QueueLookups;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\ParseMethod;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
use Modules\Genetics\Support\Extraction\MarkerExtractor;
use Modules\Genetics\Support\MarkerListReader;
use Modules\Genetics\Support\Parsing\FormatDetector;
use Modules\Genetics\Support\Parsing\MarkerCsvParser;
use Modules\Genetics\Support\Parsing\ParsedKind;
use Modules\Genetics\Support\Parsing\ParsedVariant;
use Modules\Genetics\Support\ReadOutcome;
use RuntimeException;
use Throwable;

/**
 * Parses one uploaded file into the user's profile, then deletes the raw file.
 * Status runs Queued -> Parsing -> LookingUp -> Complete, or ends Failed.
 */
class ProcessGeneticUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const NO_MARKERS = 'No genetic markers found in this file.';

    public const TOO_MANY_MARKERS = 'This file has too many markers. Upload a raw DNA export or a shorter marker list.';

    public const GENERIC_ERROR = 'Processing failed unexpectedly. Try uploading the file again.';

    private const CHUNK = 1000;

    public int $tries = 2;

    public int $backoff = 15;

    /** Room for genetics.extract.max_chunks model calls in a row; stays under the queue's retry_after. */
    public int $timeout = 200;

    public function __construct(public readonly int $uploadId) {}

    /**
     * A concurrent duplicate is dropped, not released. The lock must outlive the
     * job timeout but expire before retry_after makes the job reservable again.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->uploadId))->dontRelease()->expireAfter($this->lockSeconds())];
    }

    public function lockSeconds(): int
    {
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 240);

        return max($this->timeout + 1, $retryAfter - 30);
    }

    public function handle(FormatDetector $detector, MarkerExtractor $extractor, MarkerListReader $reader, AddVariant $addVariant, QueueLookups $queueLookups): void
    {
        $upload = GeneticUpload::find($this->uploadId);

        if ($upload === null || ! $upload->status->isActive()) {
            return;
        }

        $retrying = false;

        try {
            $this->process($upload, $detector, $extractor, $reader, $addVariant, $queueLookups);
        } catch (Throwable $e) {
            if ($this->canRetry()) {
                $retrying = true;
                $this->release($this->backoff);

                return;
            }

            // Stack traces carry call arguments, including genotypes: log the class only.
            Log::warning('Genetic upload failed', ['upload_id' => $upload->id, 'exception' => $e::class]);
            $this->markFailed($upload, self::GENERIC_ERROR);
        } finally {
            if (! $retrying) {
                $this->deleteFile($upload);
            }
        }
    }

    /** Never show raw exception messages: they can contain internal details. */
    public function failed(?Throwable $exception = null): void
    {
        $upload = GeneticUpload::find($this->uploadId);

        if ($upload === null) {
            return;
        }

        if ($upload->status->isActive()) {
            $this->markFailed($upload, self::GENERIC_ERROR);
        }

        $this->deleteFile($upload);
    }

    private function process(GeneticUpload $upload, FormatDetector $detector, MarkerExtractor $extractor, MarkerListReader $reader, AddVariant $addVariant, QueueLookups $queueLookups): void
    {
        $upload->update([
            'status' => UploadStatus::Parsing,
            'started_at' => now(),
            'error' => null,
            'found_count' => 0,
            'stored_count' => 0,
            'parse_method' => null,
            'local_count' => 0,
            'ai_count' => 0,
            'skipped_count' => 0,
        ]);

        if ($upload->path === null) {
            throw new RuntimeException('The upload has no stored file.');
        }

        $absolutePath = Storage::disk('genetics')->path($upload->path);
        $extension = strtolower(pathinfo($upload->path, PATHINFO_EXTENSION));
        $parser = $detector->detect($absolutePath, $extension);

        $store = fn (ParsedVariant $variant) => $addVariant->handle(
            $upload->user_id, $variant->rsid, $variant->genotype, $variant->gene, $upload->original_name, $upload->id,
        );

        if ($parser !== null && $parser->kind() === ParsedKind::RawExport) {
            [$found, $stored] = $this->storeKnown($parser->parse($absolutePath), $store);
            $upload->update(['parse_method' => ParseMethod::Local, 'local_count' => $found]);
        } else {
            $outcome = $parser instanceof MarkerCsvParser
                ? $reader->read($parser, $absolutePath)
                : $this->extracted($extractor, $absolutePath, $extension);

            if ($outcome === null) {
                $this->markFailed($upload, self::TOO_MANY_MARKERS);

                return;
            }

            foreach ($outcome->variants as $variant) {
                $store($variant);
            }

            $found = $stored = count($outcome->variants);
            $upload->update($outcome->counts());
        }

        if ($found === 0) {
            $this->markFailed($upload, self::NO_MARKERS);

            return;
        }

        $upload->update(['status' => UploadStatus::LookingUp, 'found_count' => $found, 'stored_count' => $stored]);

        $queued = $queueLookups->handle($this->pendingRsids($upload), (int) config('genetics.lookup_cap'));

        $upload->update(['status' => UploadStatus::Complete, 'queued_lookups' => $queued, 'completed_at' => now()]);
    }

    /**
     * The upload's rsIDs whose reference row still awaits a lookup, in a stable order.
     *
     * @return list<string>
     */
    private function pendingRsids(GeneticUpload $upload): array
    {
        $rsids = GeneVariant::where('status', LookupStatus::Pending)
            ->whereIn('rsid', UserVariant::select('rsid')->where('genetic_upload_id', $upload->id))
            ->orderBy('rsid')
            ->get(['rsid'])
            ->map(fn (GeneVariant $reference): string => $reference->rsid)
            ->all();

        return array_values($rsids);
    }

    /**
     * A raw export: keep only rsIDs already in the reference table, checked a
     * chunk at a time so a whole-genome file never sits in memory.
     *
     * @param  iterable<ParsedVariant>  $variants
     * @param  callable(ParsedVariant): mixed  $store
     * @return array{int, int} found and stored counts
     */
    private function storeKnown(iterable $variants, callable $store): array
    {
        $found = 0;
        $stored = 0;
        $chunk = [];

        foreach ($variants as $variant) {
            $found++;
            $chunk[$variant->rsid] = $variant;

            if (count($chunk) === self::CHUNK) {
                $stored += $this->storeChunk($chunk, $store);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $stored += $this->storeChunk($chunk, $store);
        }

        return [$found, $stored];
    }

    /**
     * @param  array<string, ParsedVariant>  $chunk  keyed by rsID
     * @param  callable(ParsedVariant): mixed  $store
     */
    private function storeChunk(array $chunk, callable $store): int
    {
        $known = GeneVariant::whereIn('rsid', array_keys($chunk))->pluck('rsid')->flip();
        $stored = 0;

        foreach ($chunk as $rsid => $variant) {
            if ($known->has($rsid)) {
                $store($variant);
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * A file no parser recognises (and every pdf), read by the marker extractor.
     * Null when it returns more rows than genetics.max_marker_rows. Marker lines
     * the extractor could not send (past max_chunks) are recorded as skipped.
     */
    private function extracted(MarkerExtractor $extractor, string $absolutePath, string $extension): ?ReadOutcome
    {
        $extraction = $extractor->extract($absolutePath, $extension);
        $variants = $this->boundedList($extraction->variants);

        return $variants === null ? null : new ReadOutcome($variants, ParseMethod::Ai, 0, count($variants), $extraction->unsentCount);
    }

    /**
     * An extractor result, read in full before anything is written. Null when
     * it holds more rows than genetics.max_marker_rows.
     *
     * @param  iterable<ParsedVariant>  $variants
     * @return list<ParsedVariant>|null
     */
    private function boundedList(iterable $variants): ?array
    {
        $max = (int) config('genetics.max_marker_rows');
        $list = [];

        foreach ($variants as $variant) {
            if (count($list) === $max) {
                return null;
            }

            $list[] = $variant;
        }

        return $list;
    }

    private function markFailed(GeneticUpload $upload, string $message): void
    {
        $upload->update(['status' => UploadStatus::Failed, 'error' => $message, 'completed_at' => now()]);
    }

    private function deleteFile(GeneticUpload $upload): void
    {
        if ($upload->path === null) {
            return;
        }

        Storage::disk('genetics')->delete($upload->path);
        $upload->update(['path' => null]);
    }

    /** Sync and unqueued runs cannot be released, so they fail on the first error. */
    private function canRetry(): bool
    {
        return $this->job !== null
            && ! $this->job instanceof SyncJob
            && $this->attempts() < $this->tries;
    }
}
