<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;

test(
    'task index exposes default filters in generated navigation urls',
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

        $task = Task::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'activity_id' => $activity->id,
                'status' => 'planned',
                'type' => 'call',
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
            'activity' => 'all',
            'sort' => 'updated_at',
            'direction' => 'desc',
            'per_page' => 15,
        ];

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response
            ->assertOk()
            ->assertSee(
                'href="'.e(route('tasks.create', $defaultFilters)).'"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('tasks.show', [
                            'task' => $task,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            )
            ->assertSee(
                'href="'.
                    e(
                        route('tasks.edit', [
                            'task' => $task,
                            ...$defaultFilters,
                        ]),
                    ).
                    '"',
                false,
            );
    },
);

test('task index and header navigation preserve selected filters', function () {
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

    $task = Task::factory()
        ->for($user)
        ->create([
            'name' => 'Filter Navigation Task',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'activity_id' => $activity->id,
            'status' => 'completed',
            'type' => 'email',
            'is_active' => false,
            'next_follow_up_at' => null,
        ]);

    $filters = [
        'status' => 'completed',
        'type' => 'email',
        'active' => 'inactive',
        'follow_up' => 'none',
        'company' => (string) $company->id,
        'contact' => (string) $contact->id,
        'activity' => (string) $activity->id,
        'sort' => 'name',
        'direction' => 'asc',
        'per_page' => 25,
    ];

    $indexUrl = route('tasks.index', $filters);
    $createUrl = route('tasks.create', $filters);
    $showUrl = route('tasks.show', [
        'task' => $task,
        ...$filters,
    ]);
    $editUrl = route('tasks.edit', [
        'task' => $task,
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
