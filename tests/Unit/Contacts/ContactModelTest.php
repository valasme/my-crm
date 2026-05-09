<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('contact belongs to a user', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();

    expect($contact->user)
        ->not->toBeNull()
        ->and($contact->user->is($user))
        ->toBeTrue()
        ->and($contact->user->id)
        ->toBe($contact->user_id)
        ->and($contact->user_id)
        ->toBe($user->id);
});

test('contact may belong to a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->for($user)->create();

    $contact = Contact::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    expect($contact->company)
        ->not->toBeNull()
        ->and($contact->company?->is($company))
        ->toBeTrue()
        ->and($contact->company?->id)
        ->toBe($contact->company_id)
        ->and($contact->company?->user_id)
        ->toBe($user->id);
});

test('ownedBy scope returns only contacts for the given user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $owned = Contact::factory()->count(2)->for($user)->create();
    $other = Contact::factory()->for($otherUser)->create();

    $scopedIds = Contact::query()->ownedBy($user)->pluck('id');

    expect($scopedIds->all())
        ->toContain($owned[0]->id, $owned[1]->id)
        ->not->toContain($other->id)
        ->and($scopedIds->count())
        ->toBe(2);
});

test('ownedBy scope can be chained with additional constraints', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $target = Contact::factory()
        ->for($user)
        ->create(['status' => 'customer']);

    Contact::factory()
        ->for($user)
        ->create(['status' => 'lead']);
    Contact::factory()
        ->for($otherUser)
        ->create(['status' => 'customer']);

    $resultIds = Contact::query()
        ->ownedBy($user)
        ->where('status', 'customer')
        ->pluck('id')
        ->all();

    expect($resultIds)->toContain($target->id)->and($resultIds)->toHaveCount(1);
});

test('contact casts date and boolean fields correctly', function () {
    $user = User::factory()->create();

    $contact = Contact::factory()
        ->for($user)
        ->create([
            'birthday' => '1992-08-20',
            'last_contacted_at' => '2026-03-10',
            'next_follow_up_at' => '2026-03-15',
            'is_active' => 0,
        ]);

    $contact->refresh();

    expect($contact->birthday?->format('Y-m-d'))
        ->toBe('1992-08-20')
        ->and($contact->last_contacted_at?->format('Y-m-d'))
        ->toBe('2026-03-10')
        ->and($contact->next_follow_up_at?->format('Y-m-d'))
        ->toBe('2026-03-15')
        ->and($contact->is_active)
        ->toBeFalse();
});

test('contact model exposes expected fillable attributes', function () {
    $fillable = new Contact()->getFillable();

    expect($fillable)
        ->toHaveCount(24)
        ->and($fillable)
        ->toContain('company_id')
        ->and($fillable)
        ->toContain('name')
        ->and($fillable)
        ->toContain('job_title')
        ->and($fillable)
        ->toContain('status')
        ->and($fillable)
        ->toContain('department')
        ->and($fillable)
        ->toContain('source')
        ->and($fillable)
        ->toContain('email')
        ->and($fillable)
        ->toContain('alternate_email')
        ->and($fillable)
        ->toContain('phone')
        ->and($fillable)
        ->toContain('mobile_phone')
        ->and($fillable)
        ->toContain('linkedin_url')
        ->and($fillable)
        ->toContain('preferred_contact_method')
        ->and($fillable)
        ->toContain('birthday')
        ->and($fillable)
        ->toContain('next_follow_up_at')
        ->and($fillable)
        ->toContain('is_active')
        ->and($fillable)
        ->toContain('notes')
        ->and($fillable)
        ->not->toContain('user_id');
});

test('contact model exposes expected casts', function () {
    $casts = new Contact()->getCasts();

    expect($casts['id'])
        ->toBe('int')
        ->and($casts['birthday'])
        ->toBe('date')
        ->and($casts['last_contacted_at'])
        ->toBe('date')
        ->and($casts['next_follow_up_at'])
        ->toBe('date')
        ->and($casts['is_active'])
        ->toBe('boolean')
        ->and(array_keys($casts))
        ->toBe([
            'id',
            'birthday',
            'last_contacted_at',
            'next_follow_up_at',
            'is_active',
        ]);
});

test('contact helper methods mirror model constants', function () {
    expect(Contact::statuses())
        ->toBe(Contact::STATUSES)
        ->and(Contact::statuses())
        ->toBe(['lead', 'prospect', 'customer', 'churned'])
        ->and(Contact::preferredContactMethods())
        ->toBe(Contact::PREFERRED_CONTACT_METHODS)
        ->and(Contact::preferredContactMethods())
        ->toBe(['email', 'phone', 'linkedin', 'any']);
});
