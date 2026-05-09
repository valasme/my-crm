<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;

test(
    'contact index exposes default filters in generated navigation urls',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'status' => 'lead',
                'is_active' => true,
                'next_follow_up_at' => now()->addDay(),
            ]);

        $defaultFilters = [
            'status' => 'all',
            'active' => 'all',
            'follow_up' => 'all',
            'company' => 'all',
            'sort' => 'updated_at',
            'direction' => 'desc',
            'per_page' => 15,
        ];

        $response = $this->actingAs($user)->get(route('contacts.index'));

        $response
            ->assertOk()
            ->assertSee(
                'href="'.e(route('contacts.create', $defaultFilters)).'"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('contacts.show', [
                            'contact' => $contact,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('contacts.edit', [
                            'contact' => $contact,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            );
    },
);

test(
    'contact index and header navigation preserve selected filters',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create(['name' => 'Filter Company']);

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Filter Navigation Contact',
                'company_id' => $company->id,
                'status' => 'customer',
                'is_active' => false,
                'next_follow_up_at' => null,
            ]);

        $filters = [
            'status' => 'customer',
            'active' => 'inactive',
            'follow_up' => 'none',
            'company' => (string) $company->id,
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 25,
        ];

        $indexUrl = route('contacts.index', $filters);
        $createUrl = route('contacts.create', $filters);
        $showUrl = route('contacts.show', ['contact' => $contact, ...$filters]);
        $editUrl = route('contacts.edit', ['contact' => $contact, ...$filters]);

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
