<?php

use Modules\Genetics\Enums\VariantGroup;

it('lists the groups in display order', function () {
    expect(array_map(fn (VariantGroup $g): string => $g->value, VariantGroup::cases()))->toBe([
        'methylation', 'vitamins_minerals', 'metabolism_weight', 'heart_lipids', 'brain_mood',
        'hormones', 'detox_drug_response', 'sleep_circadian', 'antioxidant_inflammation', 'other',
    ]);
});

it('has the agreed labels', function () {
    expect(array_map(fn (VariantGroup $g): string => $g->label(), VariantGroup::cases()))->toBe([
        'Methylation', 'Vitamins & minerals', 'Metabolism & weight', 'Heart & lipids', 'Brain & mood',
        'Hormones', 'Detox & drug response', 'Sleep & circadian', 'Antioxidant & inflammation', 'Other',
    ]);
});

it('maps model output to a group, or Other for anything invalid', function (mixed $value, VariantGroup $expected) {
    expect(VariantGroup::fromModel($value))->toBe($expected);
})->with([
    ['sleep_circadian', VariantGroup::SleepCircadian],
    ['Banana', VariantGroup::Other],
    ['<script>', VariantGroup::Other],
    ['', VariantGroup::Other],
    [null, VariantGroup::Other],
    [3, VariantGroup::Other],
    [['methylation'], VariantGroup::Other],
]);

it('maps each group to an icon and a tone', function () {
    expect(array_map(fn (VariantGroup $g): string => $g->icon(), VariantGroup::cases()))->toBe([
        'helix', 'capsule', 'group-flame', 'group-heart', 'group-smile',
        'group-drop', 'group-shield', 'group-moon', 'group-leaf', 'group-dots',
    ])->and(array_map(fn (VariantGroup $g): string => $g->tone(), VariantGroup::cases()))->toBe([
        'green', 'butter', 'peach', 'coral', 'blue', 'rose', 'teal', 'indigo', 'leaf', 'stone',
    ]);
});
