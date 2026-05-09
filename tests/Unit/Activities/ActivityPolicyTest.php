<?php

use App\Models\Activity;
use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    'activity policy grants listing and create permissions only to persisted users',
    function () {
        $persistedUser = User::factory()->create();
        $transientUser = User::factory()->make();

        $policy = new ActivityPolicy;

        expect($persistedUser->exists)
            ->toBeTrue()
            ->and($transientUser->exists)
            ->toBeFalse()
            ->and($policy->viewAny($persistedUser))
            ->toBeTrue()
            ->and($policy->create($persistedUser))
            ->toBeTrue()
            ->and($policy->viewAny($transientUser))
            ->toBeFalse()
            ->and($policy->create($transientUser))
            ->toBeFalse();
    },
);

test('activity policy authorizes ownership-based actions', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $activity = Activity::factory()->for($owner)->create();

    $policy = new ActivityPolicy;

    foreach (
        ['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability
    ) {
        expect($policy->{$ability}($owner, $activity))->toBeTrue();
        expect($policy->{$ability}($otherUser, $activity))->toBeFalse();
    }
});

test('activity policy evaluates ownership per activity record', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstUsersActivity = Activity::factory()->for($firstUser)->create();
    $secondUsersActivity = Activity::factory()->for($secondUser)->create();

    $policy = new ActivityPolicy;

    expect($policy->view($firstUser, $firstUsersActivity))
        ->toBeTrue()
        ->and($policy->view($firstUser, $secondUsersActivity))
        ->toBeFalse()
        ->and($policy->update($secondUser, $secondUsersActivity))
        ->toBeTrue()
        ->and($policy->update($secondUser, $firstUsersActivity))
        ->toBeFalse()
        ->and($policy->delete($firstUser, $secondUsersActivity))
        ->toBeFalse();
});
