<?php

namespace Modules\Genetics\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Support\LookupResult;
use Modules\Genetics\Support\LookupResultBuilder;
use Modules\Genetics\Support\UnusableLookupOutput;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Contracts\WebResearcher;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use Modules\Intelligence\Exceptions\ResearchProviderException;

class LookupVariant
{
    public const NO_SOURCES = 'No sources found';

    public const UNUSABLE = 'The lookup returned an unusable answer';

    public const PROVIDER_ERROR = 'The lookup could not be completed. Try again later.';

    public function __construct(
        private readonly WebResearcher $researcher,
        private readonly TextGenerator $generator,
    ) {}

    /**
     * One search and one structured call, then the reference row becomes Complete or Failed. Errors
     * stored on the row are fixed messages: provider text and model output never reach the UI.
     */
    public function handle(string $rsid): void
    {
        $reference = GeneVariant::where('rsid', $rsid)->first();

        if ($reference === null || ! in_array($reference->status, [LookupStatus::Pending, LookupStatus::LookingUp, LookupStatus::Failed], true)) {
            return;
        }

        $reference->update(['status' => LookupStatus::LookingUp, 'error' => null]);

        // A gene typed by hand is only a hint: it narrows the search and is overwritten by the result.
        $gene = $reference->gene;

        try {
            $sources = $this->researcher->search(new SearchQuery(
                query: trim("{$rsid} {$gene} SNP gene variant"),
                domains: array_values((array) config('genetics.lookup.domains')),
                limit: (int) config('genetics.lookup.limit', 5),
                scrape: true,
            ));
        } catch (ResearchProviderException) {
            $this->fail($reference, self::PROVIDER_ERROR);

            return;
        }

        if ($sources === []) {
            $this->fail($reference, self::NO_SOURCES);

            return;
        }

        try {
            $output = $this->generator->generate(PromptRequest::for(
                'genetics.lookup',
                ['rsid' => $rsid, 'gene' => $gene, 'sources' => $sources],
                $this->schema(...),
            ))->structured;

            $result = LookupResultBuilder::build($output, $sources);
        } catch (GenerationFailedException) {
            $this->fail($reference, self::PROVIDER_ERROR);

            return;
        } catch (UnusableLookupOutput) {
            $this->fail($reference, self::UNUSABLE);

            return;
        }

        $this->complete($reference, $result);
    }

    private function complete(GeneVariant $reference, LookupResult $result): void
    {
        $reference->update([
            'gene' => $result->gene ?? $reference->gene,
            'reference_allele' => $result->referenceAllele,
            'risk_allele' => $result->riskAllele,
            'category' => $result->category,
            'affects' => $result->affects,
            'genotype_effects' => $result->genotypeEffects,
            'sources' => $result->sources,
            'status' => LookupStatus::Complete,
            'error' => null,
            'looked_up_at' => now(),
        ]);
    }

    private function fail(GeneVariant $reference, string $message): void
    {
        $reference->update(['status' => LookupStatus::Failed, 'error' => $message]);
    }

    /**
     * Strict schema: every property is required; optional ones are nullable too.
     *
     * @return array<string, mixed>
     */
    private function schema(JsonSchema $s): array
    {
        return [
            'gene' => $s->string()->required(),
            'category' => $s->string()->required(),
            'affects' => $s->string()->required(),
            'reference_allele' => $s->string()->nullable()->required(),
            'risk_allele' => $s->string()->nullable()->required(),
            'genotype_effects' => $s->array()->items($s->object([
                'genotype' => $s->string()->required(),
                'effect' => $s->string()->required(),
            ]))->required(),
            'source_ids' => $s->array()->items($s->string())->required(),
        ];
    }
}
