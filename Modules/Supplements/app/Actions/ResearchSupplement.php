<?php

namespace Modules\Supplements\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Contracts\WebResearcher;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use Modules\Intelligence\Exceptions\ResearchProviderException;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Repositories\ResearchRepository;
use Modules\Supplements\Repositories\SupplementRepository;
use Modules\Supplements\Support\ResearchFindingsBuilder;
use Modules\Supplements\Support\ResearchStepFailed;
use Modules\Supplements\Support\UnusableResearchOutput;

class ResearchSupplement
{
    public function __construct(
        private readonly SupplementRepository $supplements,
        private readonly ResearchRepository $research,
        private readonly WebResearcher $researcher,
        private readonly TextGenerator $generator,
    ) {}

    /**
     * @throws ResearchStepFailed on a provider or generation error that may succeed on retry
     */
    public function handle(int $supplementId): void
    {
        $supplement = $this->supplements->find($supplementId);

        if ($supplement === null) {
            return;
        }

        $name = $supplement->name;

        $this->research->markSearching($supplementId);

        try {
            $sources = $this->researcher->search(new SearchQuery(
                query: "{$name} supplement clinical evidence",
                domains: array_values((array) config('supplements.research.domains')),
                limit: (int) config('supplements.research.limit', 8),
                scrape: true,
            ));
        } catch (ResearchProviderException $e) {
            throw new ResearchStepFailed($e->getMessage(), previous: $e);
        }

        if ($sources === []) {
            $this->research->markFailed($supplementId, "No trusted sources found for {$name}");

            return;
        }

        $this->research->markAnalyzing($supplementId);

        try {
            $result = $this->generator->generate(PromptRequest::for(
                'supplements.research',
                ['supplement' => $name, 'sources' => $sources],
                $this->schema(...),
            ));
        } catch (GenerationFailedException $e) {
            throw new ResearchStepFailed($e->getMessage(), previous: $e);
        }

        try {
            $findings = ResearchFindingsBuilder::build($result->structured, $sources);
        } catch (UnusableResearchOutput) {
            $this->research->markFailed($supplementId, 'The research model returned an unusable answer');

            return;
        }

        $this->research->replaceFindings($supplementId, $findings);
    }

    /**
     * Strict schema: every property is required; optional ones are nullable too.
     *
     * @return array<string, mixed>
     */
    private function schema(JsonSchema $s): array
    {
        $grades = array_map(fn (EvidenceGrade $g): string => $g->value, EvidenceGrade::cases());
        $ids = fn () => $s->array()->items($s->string())->required();

        return [
            'summary' => $s->string()->required(),
            'safety_notes' => $s->string()->required(),
            'effects' => $s->array()->items($s->object([
                'claim' => $s->string()->required(),
                'outcome' => $s->string()->required(),
                'evidence_grade' => $s->string()->enum($grades)->required(),
                'source_ids' => $ids(),
            ]))->required(),
            'mechanisms' => $s->array()->items($s->object([
                'description' => $s->string()->required(),
                'source_ids' => $ids(),
            ]))->required(),
            'studies' => $s->array()->items($s->object([
                'title' => $s->string()->required(),
                'design' => $s->string()->required(),
                'population' => $s->string()->nullable()->required(),
                'sample_size' => $s->integer()->nullable()->required(),
                'year' => $s->integer()->nullable()->required(),
                'finding' => $s->string()->required(),
                'evidence_grade' => $s->string()->enum($grades)->required(),
                'source_id' => $s->string()->required(),
                'pmid' => $s->string()->nullable()->required(),
            ]))->required(),
        ];
    }
}
