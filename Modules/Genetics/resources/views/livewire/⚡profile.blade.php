<?php

use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Actions\LookUpAll;
use Modules\Genetics\Actions\QueueLookups;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\UserVariant;
use Modules\Genetics\Support\VariantGrouping;
use Modules\Shared\Contracts\VariantLinks;
use Modules\Shared\Data\LinkedSupplementData;

new #[Title('My genetics')] class extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public string $rsid = '';

    public string $genotype = '';

    public string $gene = '';

    #[Url]
    public string $group = '';

    #[Url]
    public string $search = '';

    /** @var list<string> Section keys the user has opened; filtered against the allowed keys on read. */
    #[Locked]
    public array $openSections = [];

    public function mount(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
        $this->syncOpenSections();
    }

    public function updatedGroup(): void
    {
        $this->syncOpenSections();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
        $this->syncOpenSections();
    }

    /** Opens or closes one section. Keys that are not a group value or 'unlooked' are ignored. */
    public function toggleSection(string $key): void
    {
        if (! in_array($key, $this->sectionKeys(), true)) {
            return;
        }

        $open = $this->openSectionKeys;

        $this->openSections = in_array($key, $open, true)
            ? array_values(array_diff($open, [$key]))
            : [...$open, $key];

        unset($this->openSectionKeys);
    }

    public function addVariant(AddVariant $addVariant, QueueLookups $queueLookups): void
    {
        $userId = auth()->id();
        abort_if($userId === null, 401);

        $this->validate([
            'rsid' => ['required', 'string', 'regex:/^rs\d{1,10}$/i'],
            'genotype' => [
                'required', 'string', 'max:5',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        if (Genotype::normalize((string) $value) === null) {
                            $fail('Enter the genotype as one or two alleles, for example A/G.');
                        }
                    } catch (\InvalidArgumentException) {
                        $fail('Use one or two alleles from A, C, G, T, D or I.');
                    }
                },
            ],
            'gene' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{1,15}$/'],
        ], [
            'rsid.regex' => 'Use an rsID such as rs1801133.',
            'gene.regex' => 'Use a gene symbol such as MTHFR.',
        ]);

        $limiterKey = 'genetics-add:'.$userId;

        if (RateLimiter::tooManyAttempts($limiterKey, 20)) {
            throw ValidationException::withMessages(['rsid' => 'Too many requests, try again in a minute.']);
        }

        RateLimiter::hit($limiterKey, 60);

        try {
            $reference = $addVariant->handle($userId, $this->rsid, $this->genotype, $this->gene);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['rsid' => 'That SNP could not be saved. Check the values and try again.']);
        }

        if ($reference->status === LookupStatus::Pending) {
            $queueLookups->handle([$reference->rsid], 1);
        }

        $this->reset('rsid', 'genotype', 'gene');
        $this->forgetVariants();
        Flux::toast(text: 'SNP saved.', variant: 'success');
    }

    /**
     * Queues one lookup for an SNP in the user's own profile. A row that is already
     * being looked up is ignored.
     */
    public function lookUp(string $rsid, QueueLookups $queueLookups): void
    {
        $userId = auth()->id();
        abort_if($userId === null, 401);

        if (! UserVariant::where('user_id', $userId)->where('rsid', $rsid)->exists()) {
            return;
        }

        $limiterKey = 'genetics-lookup:'.$userId;

        if (RateLimiter::tooManyAttempts($limiterKey, 10)) {
            throw ValidationException::withMessages(['lookup' => 'Too many requests, try again in a minute.']);
        }

        RateLimiter::hit($limiterKey, 60);

        $queueLookups->handle([$rsid], 1);

        $this->forgetVariants();
    }

    /**
     * Queues lookups for the user's own pending and failed variants and toasts the outcome.
     * The action enforces the cap and its own rate limit.
     */
    public function lookUpAll(LookUpAll $lookUpAll): void
    {
        $userId = auth()->id();
        abort_if($userId === null, 401);

        $result = $lookUpAll->handle($userId);

        $this->forgetVariants();

        if ($result->limited) {
            Flux::toast(text: 'Too many requests, try again in a minute.', variant: 'danger');
        } elseif ($result->nothingPending || $result->queued === 0) {
            Flux::toast(text: 'Nothing to look up.');
        } else {
            Flux::toast(text: $result->queued.' '.Str::plural('variant', $result->queued).' queued for lookup.', variant: 'success');
        }
    }

    /**
     * Moves the file off Livewire's temporary area into the genetics disk under
     * a random name, then queues it for parsing. The temporary copy is always
     * deleted, including when the upload is rejected.
     */
    public function uploadResults(): void
    {
        $userId = auth()->id();
        abort_if($userId === null, 401);

        try {
            $this->validate([
                'file' => ['required', 'file', 'extensions:txt,csv,pdf', 'mimes:txt,csv,pdf', 'max:'.config('genetics.max_upload_kb')],
            ], [
                'file.extensions' => 'Upload a .txt, .csv or .pdf file.',
                'file.mimes' => 'Upload a .txt, .csv or .pdf file.',
            ]);
        } catch (ValidationException $e) {
            $this->discardFile();

            throw $e;
        }

        $limiterKey = 'genetics-upload:'.$userId;

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $this->discardFile();

            throw ValidationException::withMessages(['file' => 'Too many requests, try again in a minute.']);
        }

        RateLimiter::hit($limiterKey, 60);

        /** @var TemporaryUploadedFile $file */
        $file = $this->file;
        $path = $file->storeAs('uploads', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'genetics');
        $originalName = mb_substr($file->getClientOriginalName(), 0, 255);
        $this->discardFile();

        $upload = GeneticUpload::create([
            'user_id' => $userId,
            'original_name' => $originalName,
            'path' => $path,
            'status' => UploadStatus::Queued,
        ]);

        ProcessGeneticUpload::dispatch($upload->id);

        unset($this->uploads, $this->hasActiveUploads, $this->uploadCount);
        $this->forgetVariants();
        Flux::toast(text: 'Upload received. Processing it now.', variant: 'success');
    }

    /** Deletes the temporary upload and the metadata file Livewire keeps beside it. */
    private function discardFile(): void
    {
        if ($this->file !== null) {
            $this->file->delete();
            Storage::disk(FileUploadConfiguration::disk())->delete(FileUploadConfiguration::path($this->file->getFilename().'.json', false));
        }

        $this->reset('file');
    }

    /** @return Collection<int, GeneticUpload> */
    #[Computed]
    public function uploads(): Collection
    {
        return GeneticUpload::where('user_id', (int) auth()->id())->latest('id')->limit(10)->get();
    }

    #[Computed]
    public function hasActiveUploads(): bool
    {
        $active = array_filter(UploadStatus::cases(), fn (UploadStatus $status): bool => $status->isActive());

        return GeneticUpload::where('user_id', (int) auth()->id())->whereIn('status', $active)->exists();
    }

    /** @return list<VariantData> */
    #[Computed]
    public function variants(): array
    {
        $variants = app(GeneticProfileReader::class)->forUser((int) auth()->id())->variants;

        usort($variants, fn (VariantData $a, VariantData $b): int => [$a->gene ?? "\u{10FFFF}", $a->rsid] <=> [$b->gene ?? "\u{10FFFF}", $b->rsid]);

        return $variants;
    }

    #[Computed]
    public function hasLookingUp(): bool
    {
        foreach ($this->variants as $variant) {
            if ($variant->status === LookupStatus::LookingUp) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, list<LinkedSupplementData>> */
    #[Computed]
    public function links(): array
    {
        return app(VariantLinks::class)->forUser((int) auth()->id());
    }

    #[Computed]
    public function grouping(): VariantGrouping
    {
        return new VariantGrouping($this->variants, $this->links);
    }

    /** @return array{all: int, linked: int, two_copies: int, unlooked: int, looking_up: int, groups: array<string, int>} */
    #[Computed]
    public function counts(): array
    {
        return $this->grouping->counts();
    }

    /** The filter as a known key (a group, 'linked', 'two_copies' or 'unlooked'), or null (All) for anything else. */
    #[Computed]
    public function activeGroup(): ?string
    {
        return VariantGrouping::normalizeGroup($this->group);
    }

    /** The search text, trimmed and capped at 100 characters. */
    #[Computed]
    public function activeSearch(): string
    {
        return mb_substr(trim($this->search), 0, 100);
    }

    /**
     * The group choices: only groups that have rows, then the not-looked-up section.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function groupOptions(): array
    {
        return $this->grouping->options();
    }

    /**
     * Sections in group order with "Not looked up yet" last, after the filter and search are applied.
     *
     * @return list<array{key: string, label: string, variants: list<VariantData>}>
     */
    #[Computed]
    public function groupedVariants(): array
    {
        return $this->grouping->sections($this->activeGroup, $this->activeSearch);
    }

    #[Computed]
    public function uploadCount(): int
    {
        return GeneticUpload::where('user_id', (int) auth()->id())->where('status', '!=', UploadStatus::Failed)->count();
    }

    /** "{n} variants from {u} uploads", plus " and {m} added by hand" when any were. */
    #[Computed]
    public function sourceSummary(): string
    {
        $manual = count(array_filter($this->variants, fn (VariantData $variant): bool => $variant->source === 'manual'));
        $uploaded = count($this->variants) - $manual;

        if ($uploaded === 0) {
            return $manual === 0 ? 'No variants yet.' : $manual.' '.Str::plural('variant', $manual).' added by hand.';
        }

        $uploads = max(1, $this->uploadCount);
        $text = $uploaded.' '.Str::plural('variant', $uploaded).' from '.($uploads === 1 ? 'one upload' : $uploads.' uploads');

        return $text.($manual > 0 ? ' and '.$manual.' added by hand.' : '.');
    }

    /** @return list<string> */
    private function sectionKeys(): array
    {
        return [...array_map(fn (VariantGroup $case): string => $case->value, VariantGroup::cases()), 'unlooked'];
    }

    /** @return list<string> */
    #[Computed]
    public function openSectionKeys(): array
    {
        return array_values(array_intersect($this->sectionKeys(), array_filter($this->openSections, 'is_string')));
    }

    /** Opens the sections the current filter shows, or collapses all when no filter is active. */
    private function syncOpenSections(): void
    {
        unset($this->activeGroup, $this->activeSearch, $this->groupedVariants);

        $filtered = $this->activeGroup !== null || $this->activeSearch !== '';

        $this->openSections = $filtered ? array_column($this->groupedVariants, 'key') : [];
        unset($this->openSectionKeys);
    }

    private function forgetVariants(): void
    {
        unset($this->variants, $this->links, $this->grouping, $this->counts, $this->groupOptions, $this->groupedVariants, $this->hasLookingUp);
    }
};
?>

<div @if ($this->hasActiveUploads || $this->hasLookingUp) wire:poll.3s @endif>
<x-ui.panel>
    <header class="flex flex-col gap-1.5">
        <h1 class="text-[30px] leading-[1.15] font-bold tracking-[-0.02em] text-ink">Genetics</h1>
        <p class="text-[15px] text-ink-muted">{{ $this->sourceSummary }}</p>
        <p class="text-[15px] text-ink-muted">Raw files are deleted after processing; only marker lines ever reach the AI model.</p>
    </header>

    <x-ui.notice>Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.</x-ui.notice>

    <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(380px,100%),1fr))]">
        <section class="flex flex-col gap-3.5 rounded-section border border-line p-5">
            <h2 class="text-[18px] font-bold">Upload results</h2>
            <form wire:submit="uploadResults" class="flex flex-col gap-3"
                x-data="{ uploading: false, progress: 0 }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false"
            >
                <x-ui.dropzone
                    wire:model="file"
                    accept=".txt,.csv,.pdf"
                    label="Drop a raw data file or a PDF report"
                    help="23andMe, AncestryDNA, MyHeritage, FamilyTreeDNA, a marker CSV or a PDF · up to {{ intdiv((int) config('genetics.max_upload_kb'), 1024) }} MB"
                    :filename="$file?->getClientOriginalName()"
                />
                <div x-show="uploading" x-cloak class="h-1.5 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" aria-label="Upload progress">
                    <div class="h-full bg-juniper transition-[width]" x-bind:style="'width: ' + progress + '%'"></div>
                </div>
                <flux:error name="file" />
                <x-ui.button type="submit" loading="file,uploadResults" class="self-start">Upload</x-ui.button>
            </form>

            @foreach ($this->uploads as $upload)
                <div wire:key="upload-{{ $upload->id }}" class="flex items-center gap-3 border-t border-line-soft pt-3.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-tile bg-surface-sunken text-ink-muted"><x-icon.file class="size-5" /></span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="text-[14px] font-semibold break-all">{{ $upload->original_name }}</span>
                        <span class="text-[13px] text-ink-muted">
                            @if ($upload->readSummary() !== null){{ $upload->readSummary() }} · @endif{{ $upload->queued_lookups }} queued for lookup
                        </span>
                        <span class="sr-only">Found {{ $upload->found_count }}, stored {{ $upload->stored_count }}</span>
                        @if ($upload->error !== null)
                            <span class="text-[13px] text-bad-text">{{ $upload->error }}</span>
                        @endif
                    </span>
                    <x-ui.status :tone="$upload->status->tone()" :label="$upload->status->label()" />
                </div>
            @endforeach
        </section>

        <section class="flex flex-col gap-3.5 rounded-section border border-line p-5">
            <h2 class="text-[18px] font-bold">Add a single SNP</h2>
            <p class="text-[14px] leading-normal text-ink-muted">Know a result that wasn't in your file? Add it by hand and we'll look it up.</p>
            <form wire:submit="addVariant" class="flex flex-1 flex-col gap-3">
                <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(min(130px,100%),1fr))]">
                    <flux:input wire:model="rsid" label="rsID" placeholder="rs1801133" maxlength="12" />
                    <flux:input wire:model="genotype" label="Genotype" placeholder="A/G" maxlength="5" />
                    <flux:input wire:model="gene" label="Gene (optional)" placeholder="MTHFR" maxlength="15" />
                </div>
                <x-ui.button type="submit" variant="secondary" loading="addVariant" class="mt-auto self-start !px-[22px]">Add SNP</x-ui.button>
            </form>
        </section>
    </div>

    <section class="flex flex-col gap-3.5">
        <flux:error name="lookup" />

        @if ($this->variants === [])
            <p class="text-[15px] text-ink-muted">No SNPs yet. Add one above.</p>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[20px] font-bold">Your variants</h2>
                <label class="flex h-[46px] flex-[0_1_360px] items-center gap-2.5 rounded-full border border-line bg-surface-input px-4 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-juniper">
                    <x-icon.search class="size-[18px] shrink-0 text-ink-subtle" />
                    <span class="sr-only">Search variants</span>
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search gene, rsID or effect" maxlength="100" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-ink outline-none focus:ring-0" />
                </label>
            </div>

            <div class="flex flex-wrap gap-2" data-filter-chips>
                <x-ui.chip :active="$this->activeGroup === null" :count="$this->counts['all']" wire:click="$set('group', '')">All</x-ui.chip>
                <x-ui.chip :active="$this->activeGroup === 'linked'" :count="$this->counts['linked']" wire:click="$set('group', 'linked')">Linked to a supplement</x-ui.chip>
                <x-ui.chip :active="$this->activeGroup === 'two_copies'" :count="$this->counts['two_copies']" wire:click="$set('group', 'two_copies')">Two copies</x-ui.chip>
                <x-ui.chip :active="$this->activeGroup === 'unlooked'" :count="$this->counts['unlooked']" wire:click="$set('group', 'unlooked')">Not looked up</x-ui.chip>
                @foreach ($this->groupOptions as $value => $label)
                    @if ($value !== 'unlooked')
                        <x-ui.chip wire:key="chip-{{ $value }}" :active="$this->activeGroup === $value" :count="$this->counts['groups'][$value] ?? 0" wire:click="$set('group', '{{ $value }}')">{{ $label }}</x-ui.chip>
                    @endif
                @endforeach
            </div>

            @if ($this->groupedVariants === [])
                <p class="text-[15px] text-ink-muted">No variants match.</p>
            @else
                <x-genetics::variant-list :sections="$this->groupedVariants" :open-keys="$this->openSectionKeys" :links="$this->links" :looking-up="$this->counts['looking_up']" />
            @endif
        @endif
    </section>
</x-ui.panel>
</div>
