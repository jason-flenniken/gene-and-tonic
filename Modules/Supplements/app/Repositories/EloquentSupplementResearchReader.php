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
            geneInteractions: array_values($supplement->geneInteractions->sortBy('position')->map(fn (SupplementGeneInteraction $g): GeneInteractionData => new GeneInteractionData(
                gene: $g->gene,
                rsid: $g->rsid,
                allele: $g->allele,
                relation: $g->relation,
                explanation: $g->explanation,
                sourceKeys: $this->keys($g->source_keys),
            ))->all()),
            geneticsChecked: $research->genetics_checked_at !== null,
        );
    }

    /** @return list<string> */
    private function keys(mixed $keys): array
    {
        return is_array($keys) ? array_values(array_map(strval(...), $keys)) : [];
    }
}
