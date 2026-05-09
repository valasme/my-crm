<?php

use App\Models\Contact;
use App\Models\User;
use App\Policies\ContactPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    'contact policy grants listing and create permissions only to persisted users',
    function () {
        $persistedUser = User::factory()->create();
        $transientUser = User::factory()->make();

        $policy = new ContactPolicy;

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

test('contact policy authorizes ownership-based actions', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $contact = Contact::factory()->for($owner)->create();

    $policy = new ContactPolicy;

    foreach (
        ['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability
    ) {
        expect($policy->{$ability}($owner, $contact))->toBeTrue();
        expect($policy->{$ability}($otherUser, $contact))->toBeFalse();
    }
});

test('contact policy evaluates ownership per contact record', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstUsersContact = Contact::factory()->for($firstUser)->create();
    $secondUsersContact = Contact::factory()->for($secondUser)->create();

    $policy = new ContactPolicy;

    expect($policy->view($firstUser, $firstUsersContact))
        ->toBeTrue()
        ->and($policy->view($firstUser, $secondUsersContact))
        ->toBeFalse()
        ->and($policy->update($secondUser, $secondUsersContact))
        ->toBeTrue()
        ->and($policy->update($secondUser, $firstUsersContact))
        ->toBeFalse()
        ->and($policy->delete($firstUser, $secondUsersContact))
        ->toBeFalse();
});
