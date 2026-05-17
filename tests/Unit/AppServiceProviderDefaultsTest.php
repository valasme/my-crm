<?php

use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

uses(TestCase::class);

test('eloquent strict mode is enabled outside production', function () {
    expect(app()->isProduction())
        ->toBeFalse()
        ->and(Model::preventsLazyLoading())
        ->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())
        ->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())
        ->toBeTrue();
});
