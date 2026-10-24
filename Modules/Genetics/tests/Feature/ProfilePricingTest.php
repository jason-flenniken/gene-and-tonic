<?php

it('prices the extract and lookup profile models', function (string $profile) {
    $model = config("genetics.{$profile}_profile.model");

    expect(config('intelligence.pricing.models'))->toHaveKey($model);
})->with(['extract', 'lookup']);
