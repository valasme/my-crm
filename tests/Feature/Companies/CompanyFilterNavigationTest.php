<?php

use App\Models\Company;
use App\Models\User;

test(
    'company index exposes default filters in generated navigation urls',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create([
                'status' => 'lead',
                'is_active' => true,
                'next_follow_up_at' => now()->addDay(),
            ]);

        $defaultFilters = [
            'status' => 'all',
            'active' => 'all',
            'follow_up' => 'all',
            'sort' => 'updated_at',
            'direction' => 'desc',
            'per_page' => 15,
        ];

        $response = $this->actingAs($user)->get(route('companies.index'));

        $response
            ->assertOk()
            ->assertSee(
                'href="'.e(route('companies.create', $defaultFilters)).'"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('companies.show', [
                            'company' => $company,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('companies.edit', [
                            'company' => $company,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            );
    },
);

test(
    'company index and header navigation preserve selected filters',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Filter Navigation Company',
                'status' => 'customer',
                'is_active' => false,
                'next_follow_up_at' => null,
            ]);

        $filters = [
            'status' => 'customer',
            'active' => 'inactive',
            'follow_up' => 'none',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 25,
        ];

        $indexUrl = route('companies.index', $filters);
        $createUrl = route('companies.create', $filters);
        $showUrl = route('companies.show', [
            'company' => $company,
            ...$filters,
        ]);
        $editUrl = route('companies.edit', [
            'company' => $company,
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
