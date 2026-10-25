<?php

use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\HealthTopic;
use Modules\Supplements\Enums\ResearchStatus;

it('has the fixed health topics with labels', function () {
    expect(array_map(fn (HealthTopic $t) => $t->label(), HealthTopic::cases()))->toBe([
        'Methylation', 'Heart', 'Mood', 'Sleep', 'Stress', 'Energy', 'Focus', 'Blood', 'Immune', 'Digestion', 'Other',
    ]);
});

it('maps research status to tone and step', function () {
    expect(array_map(fn (ResearchStatus $s) => [$s->tone(), $s->step()], ResearchStatus::cases()))->toBe([
        ['neutral', 1], ['active', 2], ['active', 3], ['ok', null], ['bad', null],
    ]);
});

it('maps evidence grade to tone and rank', function () {
    expect(array_map(fn (EvidenceGrade $g) => [$g->tone(), $g->rank()], EvidenceGrade::cases()))->toBe([
        ['ok', 0], ['juniper', 1], ['warn', 2],
    ]);
});

it('maps gene relation to tone', function () {
    expect(array_map(fn (GeneRelation $r) => $r->tone(), GeneRelation::cases()))->toBe(['ok', 'warn', 'neutral']);
});
