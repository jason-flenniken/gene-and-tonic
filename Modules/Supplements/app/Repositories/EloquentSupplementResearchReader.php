<?php

namespace Modules\Supplements\Repositories;

use Carbon\CarbonImmutable;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\EffectData;
use Modules\Supplements\Data\GeneInteractionData;
use Modules\Supplements\Data\MechanismData;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Data\SupplementSummaryData;
use Modules\Supplements\Enums\HealthTopic;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementEffect;
use Modules\Supplements\Models\SupplementGeneInteraction;
use Modules\Supplements\Models\SupplementMechanism;
use Modules\Supplements\Models\SupplementSource;
use Modules\Supplements\Models\SupplementStudy;

class EloquentSupplementResearchReader implements SupplementResearchReader
{
    public function forSupplement(int $supplementId): ?SupplementResearchData
    {
        $supplement = Supplement::query()
            ->with(['research.effects', 'research.mechanisms', 'research.studies', 'research.sources', 'geneInteractions'])
            ->find($supplementId);

        $research = $supplement?->research;

        if ($supplement === null || $research === null) {
            return null;
        }

        return new SupplementResearchData(
            supplementId: $supplement->id,
            name: $supplement->name,
            status: $research->status,
            summary: $research->summary,
            safetyNotes: $research->safety_notes,
            effects: array_values($research->effects->map(fn (SupplementEffect $e): EffectData => new EffectData(
                claim: $e->claim,
                outcome: $e->outcome,
                evidenceGrade: $e->evidence_grade,
                sourceKeys: $this->keys($e->source_keys),
                title: $e->title,
                category: $e->category,
            ))->all()),
            mechanisms: array_values($research->mechanisms->map(fn (SupplementMechanism $m): MechanismData => new MechanismData(
                description: $m->description,
                sourceKeys: $this->keys($m->source_keys),
            ))->all()),
            studies: array_values($research->studies->map(fn (SupplementStudy $s): StudyData => new StudyData(
                title: $s->title,
                design: $s->design,
                population: $s->population,
                sampleSize: $s->sample_size,
                year: $s->year,
                finding: $s->finding,
                evidenceGrade: $s->evidence_grade,
                sourceKey: $s->source_key,
                pmid: $s->pmid,
            ))->all()),
            sources: array_values($research->sources->map(fn (SupplementSource $s): SourceData => new SourceData(
                key: $s->source_key,
                url: $s->url,
                title: $s->title,
                domain: $s->domain,
            ))->all()),
            completedAt: $research->completed_at !== null ? CarbonImmutable::instance($research->completed_at) : null,
            error: $research->error,
            geneInteractions: $this->interactions($supplement),
            geneticsChecked: $research->genetics_checked_at !== null,
            kicker: $research->kicker,
            topics: $this->topics($research->topics),
        );
    }

    public function all(): array
    {
        $supplements = Supplement::query()
            ->with(['research', 'geneInteractions'])
            ->withCount(['effects', 'studies', 'sources'])
            ->whereHas('research')
            ->orderBy('name')
            ->get();

        $summaries = [];

        foreach ($supplements as $supplement) {
            $research = $supplement->research;

            if ($research === null) {
                continue;
            }

            $summaries[] = new SupplementSummaryData(
                supplementId: $supplement->id,
                name: $supplement->name,
                status: $research->status,
                completedAt: $research->completed_at !== null ? CarbonImmutable::instance($research->completed_at) : null,
                effectsCount: (int) $supplement->effects_count,
                studiesCount: (int) $supplement->studies_count,
                sourcesCount: (int) $supplement->sources_count,
                topics: $this->topics($research->topics),
                geneInteractions: $this->interactions($supplement),
            );
        }

        return $summaries;
    }

    /** @return list<GeneInteractionData> */
    private function interactions(Supplement $supplement): array
    {
        return array_values($supplement->geneInteractions->sortBy('position')->map(fn (SupplementGeneInteraction $g): GeneInteractionData => new GeneInteractionData(
            gene: $g->gene,
            rsid: $g->rsid,
            allele: $g->allele,
            relation: $g->relation,
            explanation: $g->explanation,
            sourceKeys: $this->keys($g->source_keys),
        ))->all());
    }

    /** @return list<HealthTopic> */
    private function topics(mixed $topics): array
    {
        if (! is_array($topics)) {
            return [];
        }

        $cases = [];
        foreach ($topics as $topic) {
            $case = is_string($topic) ? HealthTopic::tryFrom($topic) : null;

            if ($case !== null) {
                $cases[$case->value] = $case;
            }
        }

        return array_values($cases);
    }

    /** @return list<string> */
    private function keys(mixed $keys): array
    {
        return is_array($keys) ? array_values(array_map(strval(...), $keys)) : [];
    }
}
