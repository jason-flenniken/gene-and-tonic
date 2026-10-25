<?php

use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Support\VariantGrouping;
use Modules\Shared\Data\LinkedSupplementData;

function groupingVariant(string $rsid, string $genotype, ?string $risk, LookupStatus $status = LookupStatus::Complete, ?VariantGroup $group = VariantGroup::Methylation): VariantData
{
    return new VariantData($rsid, 'G'.$rsid, $genotype, null, $risk, null, null, null, $status, 'manual', $group);
}

it('counts risk copies from the genotype letters', function () {
    expect(VariantGrouping::copies(groupingVariant('rs1', 'AA', 'A')))->toBe(2)
        ->and(VariantGrouping::copies(groupingVariant('rs1', 'A/G', 'A')))->toBe(1)
        ->and(VariantGrouping::copies(groupingVariant('rs1', 'GG', 'A')))->toBe(0)
        ->and(VariantGrouping::copies(groupingVariant('rs1', 'AA', null)))->toBe(0);
});

it('normalizes filter values and drops unknown ones', function () {
    expect(VariantGrouping::normalizeGroup('linked'))->toBe('linked')
        ->and(VariantGrouping::normalizeGroup('methylation'))->toBe('methylation')
        ->and(VariantGrouping::normalizeGroup('<script>'))->toBeNull()
        ->and(VariantGrouping::normalizeGroup(''))->toBeNull();
});

it('counts and sections variants, with unlooked last and Other for ungrouped', function () {
    $grouping = new VariantGrouping([
        groupingVariant('rs1', 'AA', 'A'),
        groupingVariant('rs2', 'AG', 'A', group: null),
        groupingVariant('rs3', 'AA', null, LookupStatus::Pending, null),
        groupingVariant('rs4', 'AA', null, LookupStatus::LookingUp, null),
    ], ['rs2' => [new LinkedSupplementData(1, 'X')]]);

    expect($grouping->counts())->toMatchArray(['all' => 4, 'linked' => 1, 'two_copies' => 1, 'unlooked' => 2, 'looking_up' => 1])
        ->and(array_column($grouping->sections(), 'key'))->toBe(['methylation', 'other', 'unlooked'])
        ->and(array_column($grouping->sections('linked'), 'key'))->toBe(['other'])
        ->and(array_keys($grouping->options()))->toBe(['methylation', 'other', 'unlooked']);
});
