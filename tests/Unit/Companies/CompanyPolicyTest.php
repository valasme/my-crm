<?php

use App\Models\Company;
use App\Models\User;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    "company policy grants listing and create permissions only to persisted users",
    function () {
        $persistedUser = User::factory()->create();
        $transientUser = User::factory()->make();

        $policy = new CompanyPolicy();

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

test("company policy authorizes ownership-based actions", function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $company = Company::factory()->for($owner)->create();

    $policy = new CompanyPolicy();

    foreach (
        ["view", "update", "delete", "restore", "forceDelete"]
        as $ability
    ) {
        expect($policy->{$ability}($owner, $company))->toBeTrue();
        expect($policy->{$ability}($otherUser, $company))->toBeFalse();
    }
});

test("company policy evaluates ownership per company record", function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstUsersCompany = Company::factory()->for($firstUser)->create();
    $secondUsersCompany = Company::factory()->for($secondUser)->create();

    $policy = new CompanyPolicy();

    expect($policy->view($firstUser, $firstUsersCompany))
        ->toBeTrue()
        ->and($policy->view($firstUser, $secondUsersCompany))
        ->toBeFalse()
        ->and($policy->update($secondUser, $secondUsersCompany))
        ->toBeTrue()
        ->and($policy->update($secondUser, $firstUsersCompany))
        ->toBeFalse()
        ->and($policy->delete($firstUser, $secondUsersCompany))
        ->toBeFalse();
});
