<?php

use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Actions\QueueLookups;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\UserVariant;

new #[Title('My genetics')] class extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public string $rsid = '';

    public string $genotype = '';

    public string $gene = '';

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
        unset($this->variants, $this->hasLookingUp);
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

        unset($this->variants, $this->hasLookingUp);
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

        unset($this->uploads, $this->hasActiveUploads, $this->variants);
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

    public function isLookedUp(VariantData $variant): bool
    {
        return ! in_array($variant->status, [LookupStatus::Pending, LookupStatus::LookingUp, LookupStatus::Failed], true);
    }
};
?>

<div class="space-y-6" @if ($this->hasActiveUploads || $this->hasLookingUp) wire:poll.3s @endif>
    <flux:callout icon="information-circle">
        <flux:callout.text>Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.</flux:callout.text>
    </flux:callout>

    <flux:card class="space-y-4">
        <form wire:submit="uploadResults" class="grid items-start gap-3 md:grid-cols-4">
            <div class="md:col-span-3">
                <flux:input
                    type="file"
                    wire:model="file"
                    accept=".txt,.csv,.pdf"
                    label="Upload results"
                    description="A 23andMe, AncestryDNA, MyHeritage or FamilyTreeDNA raw data file, a CSV of markers, or a PDF report. TXT, CSV or PDF, up to {{ intdiv((int) config('genetics.max_upload_kb'), 1024) }} MB."
                />
            </div>
            <flux:button type="submit" variant="primary" class="mt-6" wire:loading.attr="disabled" wire:target="file,uploadResults">Upload</flux:button>
        </form>

        @if ($this->uploads->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pr-4 font-medium">File</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Found</th>
                            <th class="py-2 pr-4 font-medium">Stored</th>
                            <th class="py-2 font-medium">Queued for lookup</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->uploads as $upload)
                            <tr wire:key="upload-{{ $upload->id }}">
                                <td class="py-2 pr-4 break-all">{{ $upload->original_name }}</td>
                                <td class="py-2 pr-4">
                                    {{ $upload->status->label() }}
                                    @if ($upload->readSummary() !== null)
                                        <span class="block text-zinc-500">{{ $upload->readSummary() }}</span>
                                    @endif
                                    @if ($upload->error !== null)
                                        <span class="block text-red-600 dark:text-red-400">{{ $upload->error }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $upload->found_count }}</td>
                                <td class="py-2 pr-4">{{ $upload->stored_count }}</td>
                                <td class="py-2">{{ $upload->queued_lookups }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    <flux:card>
        <form wire:submit="addVariant" class="grid items-start gap-3 md:grid-cols-4">
            <flux:input wire:model="rsid" label="rsID" placeholder="rs1801133" maxlength="12" />
            <flux:input wire:model="genotype" label="Genotype" placeholder="A/G" maxlength="5" />
            <flux:input wire:model="gene" label="Gene (optional)" placeholder="MTHFR" maxlength="15" />
            <flux:button type="submit" variant="primary" class="mt-6">Add SNP</flux:button>
        </form>
    </flux:card>

    <flux:card>
        <flux:error name="lookup" />

        @if ($this->variants === [])
            <flux:text>No SNPs yet. Add one above.</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Gene</th>
                            <th class="py-2 pr-4 font-medium">rsID</th>
                            <th class="py-2 pr-4 font-medium">Genotype</th>
                            <th class="py-2 pr-4 font-medium">Category</th>
                            <th class="py-2 pr-4 font-medium">Affects</th>
                            <th class="py-2 pr-4 font-medium">Your genotype</th>
                            <th class="py-2 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->variants as $variant)
                            <tr wire:key="variant-{{ $variant->rsid }}">
                                <td class="py-2 pr-4 font-medium">{{ $variant->gene }}</td>
                                <td class="py-2 pr-4">{{ $variant->rsid }}</td>
                                <td class="py-2 pr-4">{{ $variant->genotype }}</td>
                                @if ($this->isLookedUp($variant))
                                    <td class="py-2 pr-4">{{ $variant->category }}</td>
                                    <td class="py-2 pr-4">{{ $variant->affects }}</td>
                                    <td class="py-2 pr-4">{{ $variant->genotypeEffect }}</td>
                                    <td class="py-2"></td>
                                @else
                                    <td class="py-2 pr-4 text-zinc-500" colspan="3">
                                        @if ($variant->status === LookupStatus::LookingUp)
                                            Looking up…
                                        @elseif ($variant->status === LookupStatus::Failed)
                                            Lookup failed
                                        @else
                                            Not looked up yet
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        @if ($variant->status !== LookupStatus::LookingUp)
                                            <flux:button size="sm" wire:click="lookUp('{{ $variant->rsid }}')">Look up</flux:button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
