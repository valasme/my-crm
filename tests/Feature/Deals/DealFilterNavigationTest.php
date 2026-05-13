<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;

test(
    'deal index exposes default filters in generated navigation urls',
    function () {
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

        $deal = Deal::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'activity_id' => $activity->id,
                'status' => 'lead',
                'type' => 'new_business',
                'is_active' => true,
                'next_follow_up_at' => now()->addDay(),
            ]);

        $defaultFilters = [
            'status' => 'all',
            'type' => 'all',
            'active' => 'all',
            'follow_up' => 'all',
            'company' => 'all',
            'contact' => 'all',

            'sort' => 'updated_at',
            'direction' => 'desc',
            'per_page' => 15,
        ];

        $response = $this->actingAs($user)->get(route('deals.index'));

        $response
            ->assertOk()
            ->assertSee(
                'href="'.e(route('deals.create', $defaultFilters)).'"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('deals.show', [
                            'deal' => $deal,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('deals.edit', [
                            'deal' => $deal,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            );
    },
);

test('deal index and header navigation preserve selected filters', function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create(['name' => 'Filter Company']);

    $contact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $company->id,
            'name' => 'Filter Contact',
        ]);

    $activity = Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Filter Activity',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

    $deal = Deal::factory()
        ->for($user)
        ->create([
            'name' => 'Filter Navigation Deal',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'activity_id' => $activity->id,
            'status' => 'won',
            'type' => 'expansion',
            'is_active' => false,
            'next_follow_up_at' => null,
            'closed_at' => now()->toDateString(),
        ]);

    $filters = [
        'status' => 'won',
        'type' => 'expansion',
        'active' => 'inactive',
        'follow_up' => 'none',
        'company' => (string) $company->id,
        'contact' => (string) $contact->id,
        'sort' => 'name',
        'direction' => 'asc',
        'per_page' => 25,
    ];

    $indexUrl = route('deals.index', $filters);
    $createUrl = route('deals.create', $filters);
    $showUrl = route('deals.show', [
        'deal' => $deal,
        ...$filters,
    ]);
    $editUrl = route('deals.edit', [
        'deal' => $deal,
        ...$filters,
    ]);

    $indexResponse = $this->actingAs($user)->get($indexUrl);

    $indexResponse
        ->assertOk()
        ->assertSee('href="'.e($createUrl).'"', false)
        ->assertSee('href="'.e($showUrl).'"', false)
        ->assertSee('href="'.e($editUrl).'"', false);

    $showResponse = $this->actingAs($user)->get($showUrl);

    $showResponse
        ->assertOk()
        ->assertSee('href="'.e($indexUrl).'"', false)
        ->assertSee('href="'.e($editUrl).'"', false);

    $editResponse = $this->actingAs($user)->get($editUrl);

    $editResponse
        ->assertOk()
        ->assertSee('href="'.e($indexUrl).'"', false)
        ->assertSee('href="'.e($showUrl).'"', false);

    $createResponse = $this->actingAs($user)->get($createUrl);

    $createResponse
        ->assertOk()
        ->assertSee('href="'.e($indexUrl).'"', false);
});
