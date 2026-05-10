<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('activity belongs to a user', function () {
    $user = User::factory()->create();
    $activity = Activity::factory()->for($user)->create();

    expect($activity->user)
        ->not->toBeNull()
        ->and($activity->user->is($user))
        ->toBeTrue()
        ->and($activity->user->id)
        ->toBe($activity->user_id)
        ->and($activity->user_id)
        ->toBe($user->id);
});

test('activity may belong to a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->for($user)->create();

    $activity = Activity::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    expect($activity->company)
        ->not->toBeNull()
        ->and($activity->company?->is($company))
        ->toBeTrue()
        ->and($activity->company?->id)
        ->toBe($activity->company_id)
        ->and($activity->company?->user_id)
        ->toBe($user->id);
});

test('activity may belong to a contact', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();
    $contact = Contact::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    $activity = Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

    expect($activity->contact)
        ->not->toBeNull()
        ->and($activity->contact?->is($contact))
        ->toBeTrue()
        ->and($activity->contact?->id)
        ->toBe($activity->contact_id)
        ->and($activity->contact?->user_id)
        ->toBe($user->id);
});

test('ownedBy scope returns only activities for the given user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $owned = Activity::factory()->count(2)->for($user)->create();
    $other = Activity::factory()->for($otherUser)->create();

    $scopedIds = Activity::query()->ownedBy($user)->pluck('id');

    expect($scopedIds->all())
        ->toContain($owned[0]->id, $owned[1]->id)
        ->not->toContain($other->id)
        ->and($scopedIds->count())
        ->toBe(2);
});

test('ownedBy scope can be chained with additional constraints', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $target = Activity::factory()
        ->for($user)
        ->create(['status' => 'completed']);

    Activity::factory()
        ->for($user)
        ->create(['status' => 'planned']);
    Activity::factory()
        ->for($otherUser)
        ->create(['status' => 'completed']);

    $resultIds = Activity::query()
        ->ownedBy($user)
        ->where('status', 'completed')
        ->pluck('id')
        ->all();

    expect($resultIds)->toContain($target->id)->and($resultIds)->toHaveCount(1);
});

test('activity casts date fields correctly', function () {
    $user = User::factory()->create();

    $activity = Activity::factory()
        ->for($user)
        ->create([
            'activity_at' => '2026-03-10',
        ]);

    $activity->refresh();

    expect($activity->activity_at?->format('Y-m-d'))->toBe('2026-03-10');
});

test('activity model exposes expected fillable attributes', function () {
    $fillable = new Activity()->getFillable();

    expect($fillable)
        ->toHaveCount(8)
        ->and($fillable)
        ->toContain('company_id')
        ->and($fillable)
        ->toContain('contact_id')
        ->and($fillable)
        ->toContain('name')
        ->and($fillable)
        ->toContain('type')
        ->and($fillable)
        ->toContain('status')
        ->and($fillable)
        ->toContain('source')
        ->and($fillable)
        ->toContain('activity_at')
        ->and($fillable)
        ->toContain('notes')
        ->and($fillable)
        ->not->toContain('user_id');
});

test('activity model exposes expected casts', function () {
    $casts = new Activity()->getCasts();

    expect($casts['id'])
        ->toBe('int')
        ->and($casts['activity_at'])
        ->toBe('date')
        ->and(array_keys($casts))
        ->toBe(['id', 'activity_at']);
});

test('activity helper methods mirror model constants', function () {
    expect(Activity::statuses())
        ->toBe(Activity::STATUSES)
        ->and(Activity::statuses())
        ->toBe(['planned', 'completed', 'canceled'])
        ->and(Activity::types())
        ->toBe(Activity::TYPES)
        ->and(Activity::types())
        ->toBe(['call', 'email', 'meeting', 'note']);
});
