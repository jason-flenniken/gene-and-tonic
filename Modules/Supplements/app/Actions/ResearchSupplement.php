<?php

namespace Modules\Supplements\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Contracts\WebResearcher;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use Modules\Intelligence\Exceptions\ResearchProviderException;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\HealthTopic;
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

        $sources = $this->withGeneSources($name, $sources);

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
     * Second search for gene-specific sources on the same domains. A failure here is not fatal:
     * the run continues with the first search's sources. Merged sources are de-duplicated by URL
     * and re-numbered S1..Sn so the single generate call sees contiguous ids.
     *
     * @param  list<SourceDocument>  $sources
     * @return list<SourceDocument>
     */
    private function withGeneSources(string $name, array $sources): array
    {
        try {
            $geneSources = $this->researcher->search(new SearchQuery(
                query: sprintf((string) config('supplements.research.gene_query'), $name),
                domains: array_values((array) config('supplements.research.domains')),
                limit: (int) config('supplements.research.gene_limit', 4),
                scrape: true,
            ));
        } catch (ResearchProviderException) {
            $geneSources = [];
        }

        $merged = [];
        foreach ([...$sources, ...$geneSources] as $source) {
            $merged[$source->url] ??= $source;
        }

        $numbered = [];
        foreach (array_values($merged) as $index => $source) {
            $numbered[] = new SourceDocument('S'.($index + 1), $source->url, $source->title, $source->domain, $source->description, $source->markdown);
        }

        return $numbered;
    }

    /**
     * Strict schema: every property is required; optional ones are nullable too.
     *
     * @return array<string, mixed>
     */
    private function schema(JsonSchema $s): array
    {
        $grades = array_map(fn (EvidenceGrade $g): string => $g->value, EvidenceGrade::cases());
        $topics = array_map(fn (HealthTopic $t): string => $t->value, HealthTopic::cases());
        $ids = fn () => $s->array()->items($s->string())->required();

        return [
            'summary' => $s->string()->required(),
            'safety_notes' => $s->string()->required(),
            'kicker' => $s->string()->nullable()->required(),
            'topics' => $s->array()->items($s->string()->enum($topics))->required(),
            'effects' => $s->array()->items($s->object([
                'claim' => $s->string()->required(),
                'title' => $s->string()->nullable()->required(),
                'category' => $s->string()->enum($topics)->nullable()->required(),
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
            'gene_interactions' => $s->array()->items($s->object([
                'gene' => $s->string()->required(),
                'rsid' => $s->string()->nullable()->required(),
                'allele' => $s->string()->nullable()->required(),
                'relation' => $s->string()->enum(array_map(fn (GeneRelation $r): string => $r->value, GeneRelation::cases()))->required(),
                'explanation' => $s->string()->required(),
                'source_ids' => $ids(),
            ]))->required(),
        ];
    }
}
