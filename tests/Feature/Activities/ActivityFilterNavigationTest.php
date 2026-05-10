<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;

test(
    'activity index exposes default filters in generated navigation urls',
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
                'status' => 'planned',
                'type' => 'call',
            ]);

        $defaultFilters = [
            'status' => 'all',
            'type' => 'all',
            'company' => 'all',
            'contact' => 'all',
            'sort' => 'updated_at',
            'direction' => 'desc',
            'per_page' => 15,
        ];

        $response = $this->actingAs($user)->get(route('activities.index'));

        $response
            ->assertOk()
            ->assertSee(
                'href="'.e(route('activities.create', $defaultFilters)).'"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('activities.show', [
                            'activity' => $activity,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('activities.edit', [
                            'activity' => $activity,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            );
    },
);

test(
    'activity index and header navigation preserve selected filters',
    function () {
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
                'name' => 'Filter Navigation Activity',
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'status' => 'completed',
                'type' => 'email',
            ]);

        $filters = [
            'status' => 'completed',
            'type' => 'email',
            'company' => (string) $company->id,
            'contact' => (string) $contact->id,
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 25,
        ];

        $indexUrl = route('activities.index', $filters);
        $createUrl = route('activities.create', $filters);
        $showUrl = route('activities.show', [
            'activity' => $activity,
            ...$filters,
        ]);
        $editUrl = route('activities.edit', [
            'activity' => $activity,
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
    },
);
