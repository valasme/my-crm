<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('company belongs to a user', function () {
    $user = User::factory()->create();
    $company = Company::factory()->for($user)->create();

    expect($company->user)
        ->not->toBeNull()
        ->and($company->user->is($user))
        ->toBeTrue()
        ->and($company->user->id)
        ->toBe($company->user_id)
        ->and($company->user_id)
        ->toBe($user->id);
});

test('company may belong to a primary contact', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();

    $primaryContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $company->id,
        ]);

    $company->update(['primary_contact_id' => $primaryContact->id]);

    expect($company->fresh()?->primaryContact)
        ->not->toBeNull()
        ->and($company->fresh()?->primaryContact?->is($primaryContact))
        ->toBeTrue()
        ->and($company->fresh()?->primaryContact?->company_id)
        ->toBe($company->id);
});

test('ownedBy scope returns only companies for the given user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $owned = Company::factory()->count(2)->for($user)->create();
    $other = Company::factory()->for($otherUser)->create();

    $scopedIds = Company::query()->ownedBy($user)->pluck('id');

    expect($scopedIds->all())
        ->toContain($owned[0]->id, $owned[1]->id)
        ->not->toContain($other->id)
        ->and($scopedIds->count())
        ->toBe(2);
});

test('ownedBy scope can be chained with additional constraints', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $target = Company::factory()
        ->for($user)
        ->create(['status' => 'customer']);

    Company::factory()
        ->for($user)
        ->create(['status' => 'lead']);
    Company::factory()
        ->for($otherUser)
        ->create(['status' => 'customer']);

    $resultIds = Company::query()
        ->ownedBy($user)
        ->where('status', 'customer')
        ->pluck('id')
        ->all();

    expect($resultIds)->toContain($target->id)->and($resultIds)->toHaveCount(1);
});

test('company casts numeric, date, and boolean fields correctly', function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create([
            'founded_year' => 2018,
            'employee_count' => 420,
            'annual_revenue' => 1250000.5,
            'last_contacted_at' => '2026-03-10',
            'next_follow_up_at' => '2026-03-15',
            'is_active' => 0,
        ]);

    $company->refresh();

    expect($company->founded_year)
        ->toBe(2018)
        ->and(is_int($company->founded_year))
        ->toBeTrue()
        ->and($company->employee_count)
        ->toBe(420)
        ->and(is_int($company->employee_count))
        ->toBeTrue()
        ->and($company->annual_revenue)
        ->toBe('1250000.50')
        ->and($company->last_contacted_at?->format('Y-m-d'))
        ->toBe('2026-03-10')
        ->and($company->next_follow_up_at?->format('Y-m-d'))
        ->toBe('2026-03-15')
        ->and($company->is_active)
        ->toBeFalse();
});

test('company model exposes expected fillable attributes', function () {
    $fillable = new Company()->getFillable();

    expect($fillable)
        ->toHaveCount(29)
        ->and($fillable)
        ->toContain('primary_contact_id')
        ->and($fillable)
        ->toContain('name')
        ->and($fillable)
        ->toContain('legal_name')
        ->and($fillable)
        ->toContain('status')
        ->and($fillable)
        ->toContain('industry')
        ->and($fillable)
        ->toContain('source')
        ->and($fillable)
        ->toContain('ownership_type')
        ->and($fillable)
        ->toContain('founded_year')
        ->and($fillable)
        ->toContain('employee_count')
        ->and($fillable)
        ->toContain('annual_revenue')
        ->and($fillable)
        ->toContain('website')
        ->and($fillable)
        ->toContain('linkedin_url')
        ->and($fillable)
        ->toContain('email')
        ->and($fillable)
        ->toContain('billing_email')
        ->and($fillable)
        ->toContain('next_follow_up_at')
        ->and($fillable)
        ->toContain('is_active')
        ->and($fillable)
        ->toContain('notes')
        ->and($fillable)
        ->not->toContain('primary_contact_name')
        ->and($fillable)
        ->not->toContain('primary_contact_email')
        ->and($fillable)
        ->not->toContain('primary_contact_phone')
        ->and($fillable)
        ->not->toContain('user_id');
});

test('company model exposes expected casts', function () {
    $casts = new Company()->getCasts();

    expect($casts['id'])
        ->toBe('int')
        ->and($casts['primary_contact_id'])
        ->toBe('integer')
        ->and($casts['founded_year'])
        ->toBe('integer')
        ->and($casts['annual_revenue'])
        ->toBe('decimal:2')
        ->and($casts['employee_count'])
        ->toBe('integer')
        ->and($casts['last_contacted_at'])
        ->toBe('date')
        ->and($casts['next_follow_up_at'])
        ->toBe('date')
        ->and($casts['is_active'])
        ->toBe('boolean')
        ->and(array_keys($casts))
        ->toBe([
            'id',
            'primary_contact_id',
            'founded_year',
            'annual_revenue',
            'employee_count',
            'last_contacted_at',
            'next_follow_up_at',
            'is_active',
        ]);
});

test('company helper methods mirror model constants', function () {
    expect(Company::statuses())
        ->toBe(Company::STATUSES)
        ->and(Company::statuses())
        ->toBe(['lead', 'prospect', 'customer', 'churned'])
        ->and(Company::preferredContactMethods())
        ->toBe(Company::PREFERRED_CONTACT_METHODS)
        ->and(Company::preferredContactMethods())
        ->toBe(['email', 'phone', 'linkedin', 'any']);
});
