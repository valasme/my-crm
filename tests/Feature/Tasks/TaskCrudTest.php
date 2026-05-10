<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

function taskPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->sentence(3),
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Inbound',
            'task_at' => '2026-01-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => '1',
            'outcome' => 'Discussed scope and agreed next steps.',
            'notes' => 'Follow-up task for Q1 plan.',
        ],
        $overrides,
    );
}

function assertTaskAppearsBefore(
    string $content,
    string $first,
    string $second,
): void {
    $firstPosition = strpos($content, $first);
    $secondPosition = strpos($content, $second);

    expect($firstPosition)
        ->not->toBeFalse()
        ->and($secondPosition)
        ->not->toBeFalse();

    expect($firstPosition)->toBeLessThan($secondPosition);
}

test('guests are redirected from all task routes', function () {
    $task = Task::factory()->create();

    $this->get(route('tasks.index'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('tasks.create'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->post(route('tasks.store'), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('tasks.show', $task))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('tasks.edit', $task))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->put(route('tasks.update', $task), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->delete(route('tasks.destroy', $task))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

test(
    'index lists only authenticated users tasks and returns default view metadata',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create(['name' => 'Visible Co']);
        $otherCompany = Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Hidden Co']);

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'name' => 'Visible Contact',
            ]);

        $otherContact = Contact::factory()
            ->for($otherUser)
            ->create([
                'company_id' => $otherCompany->id,
                'name' => 'Hidden Contact',
            ]);

        $visible = Task::factory()
            ->for($user)
            ->create([
                'name' => 'Visible Task',
                'company_id' => $company->id,
                'contact_id' => $contact->id,
            ]);

        $hidden = Task::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Hidden Task',
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
            ]);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response
            ->assertOk()
            ->assertSee('Visible Task')
            ->assertDontSee('Hidden Task');

        $response
            ->assertSee('Status: All')
            ->assertSee('Type: All')
            ->assertSee('State: All')
            ->assertSee('Company: All companies')
            ->assertSee('Contact: All contacts')
            ->assertSee('Follow-up: All')
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending')
            ->assertSee('Rows: 15')
            ->assertSee('Planned')
            ->assertSee('Completed')
            ->assertSee('Canceled')
            ->assertSee('Call')
            ->assertSee('Email')
            ->assertSee('Meeting')
            ->assertSee('Note');

        expect(Task::query()->ownedBy($user)->pluck('id')->all())
            ->toContain($visible->id)
            ->not->toContain($hidden->id);
    },
);

test(
    'index sanitizes search input and whitelists invalid filters',
    function () {
        $user = User::factory()->create();

        Task::factory()->count(2)->for($user)->create();

        $response = $this->actingAs($user)->get(
            route('tasks.index', [
                'search' => '  <script>alert(1)</script>   Alpha   Beta  ',
                'status' => 'drop-table',
                'type' => 'drop-table',
                'active' => 'sometimes',
                'company' => 'drop-table',
                'contact' => 'drop-table',
                'follow_up' => 'later',
                'sort' => 'not-allowed',
                'direction' => 'sideways',
                'per_page' => 999,
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('value="alert(1) Alpha Beta"', false)
            ->assertSee('Status: All')
            ->assertSee('Type: All')
            ->assertSee('State: All')
            ->assertSee('Company: All companies')
            ->assertSee('Contact: All contacts')
            ->assertSee('Follow-up: All')
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending')
            ->assertSee('Rows: 15');
    },
);

test(
    'index supports searching only within the authenticated users data',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Task::factory()
            ->for($user)
            ->create(['name' => 'Orion Task']);

        Task::factory()
            ->for($user)
            ->create(['name' => 'Northwind Task']);

        Task::factory()
            ->for($otherUser)
            ->create(['name' => 'Orion Hidden']);

        $response = $this->actingAs($user)->get(
            route('tasks.index', ['search' => 'Orion']),
        );

        $response
            ->assertOk()
            ->assertSee('Orion Task')
            ->assertDontSee('Northwind Task')
            ->assertDontSee('Orion Hidden');

        $response->assertSee('value="Orion"', false);
    },
);

test(
    'index search requires all terms and ignores terms after the sixth',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $usersCompany = Company::factory()
            ->for($user)
            ->create(['name' => 'Aurora Corp']);

        $usersContact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $usersCompany->id,
                'name' => 'Aurora Contact',
            ]);

        $otherCompany = Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Aurora Corp']);

        $otherContact = Contact::factory()
            ->for($otherUser)
            ->create([
                'company_id' => $otherCompany->id,
                'name' => 'Aurora Hidden Contact',
            ]);

        $matching = Task::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Systems',
                'type' => 'call',
                'status' => 'planned',
                'source' => 'Inbound',
                'outcome' => 'Japan confirmed',
                'company_id' => $usersCompany->id,
                'contact_id' => $usersContact->id,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Partial',
                'type' => 'call',
                'status' => 'planned',
                'source' => 'Inbound',
                'outcome' => 'Brazil pending',
                'company_id' => $usersCompany->id,
                'contact_id' => $usersContact->id,
            ]);

        Task::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Aurora Hidden',
                'type' => 'call',
                'status' => 'planned',
                'source' => 'Inbound',
                'outcome' => 'Japan confirmed',
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
            ]);

        $search =
            'Aurora call planned inbound japan confirmed seventh-term-ignored';

        $response = $this->actingAs($user)->get(
            route('tasks.index', ['search' => $search]),
        );

        $response
            ->assertOk()
            ->assertSee('Aurora Systems')
            ->assertDontSee('Aurora Partial')
            ->assertDontSee('Aurora Hidden');

        expect($matching->fresh())
            ->not->toBeNull()
            ->and($matching->fresh()->name)
            ->toBe('Aurora Systems');
    },
);

test(
    'index paginates task records with default and custom per-page values',
    function () {
        $user = User::factory()->create();

        foreach (range(1, 32) as $index) {
            Task::factory()
                ->for($user)
                ->create([
                    'name' => "Pagination Task {$index}",
                    'updated_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                    'created_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                ]);
        }

        $defaultResponse = $this->actingAs($user)->get(route('tasks.index'));
        $customResponse = $this->actingAs($user)->get(
            route('tasks.index', ['per_page' => 25]),
        );
        $invalidResponse = $this->actingAs($user)->get(
            route('tasks.index', ['per_page' => 999]),
        );

        $defaultResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertSee('Pagination Task 32')
            ->assertSee('Pagination Task 18')
            ->assertDontSee('Pagination Task 17');

        $customResponse
            ->assertOk()
            ->assertSee('Rows: 25')
            ->assertSee('Pagination Task 17')
            ->assertDontSee('Pagination Task 7');

        $invalidResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertDontSee('Pagination Task 17');
    },
);

test(
    'index filters by status, type, state, company, contact, and follow-up date',
    function () {
        $user = User::factory()->create();

        $matchingCompany = Company::factory()
            ->for($user)
            ->create(['name' => 'Match Company']);

        $matchingContact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $matchingCompany->id,
                'name' => 'Match Contact',
            ]);

        $otherCompany = Company::factory()
            ->for($user)
            ->create(['name' => 'Other Company']);

        $otherContact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $otherCompany->id,
                'name' => 'Other Contact',
            ]);

        $matching = Task::factory()
            ->for($user)
            ->create([
                'name' => 'Match Task',
                'status' => 'completed',
                'type' => 'email',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Status',
                'status' => 'planned',
                'type' => 'email',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Type',
                'status' => 'completed',
                'type' => 'call',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong State',
                'status' => 'completed',
                'type' => 'email',
                'is_active' => true,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Company',
                'status' => 'completed',
                'type' => 'email',
                'is_active' => false,
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
                'next_follow_up_at' => null,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Contact',
                'status' => 'completed',
                'type' => 'email',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $otherContact->id,
                'next_follow_up_at' => null,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Has Follow Up',
                'status' => 'completed',
                'type' => 'email',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => now()->addDay(),
            ]);

        $response = $this->actingAs($user)->get(
            route('tasks.index', [
                'status' => 'completed',
                'type' => 'email',
                'active' => 'inactive',
                'company' => (string) $matchingCompany->id,
                'contact' => (string) $matchingContact->id,
                'follow_up' => 'none',
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('Match Task')
            ->assertDontSee('Wrong Status')
            ->assertDontSee('Wrong Type')
            ->assertDontSee('Wrong State')
            ->assertDontSee('Wrong Company')
            ->assertDontSee('Wrong Contact')
            ->assertDontSee('Has Follow Up');

        $response
            ->assertSee('Status: Completed')
            ->assertSee('Type: Email')
            ->assertSee('State: Inactive')
            ->assertSee('Company: Match Company')
            ->assertSee('Contact: Match Contact')
            ->assertSee('Follow-up: No date');

        expect($matching->fresh())
            ->not->toBeNull()
            ->and($matching->fresh()->name)
            ->toBe('Match Task');
    },
);

test('index follow-up filter supports due and upcoming buckets', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-10 09:00:00'));

    try {
        $user = User::factory()->create();

        $duePast = Task::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDuePastTask',
                'next_follow_up_at' => '2026-02-09',
            ]);

        $dueToday = Task::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDueTodayTask',
                'next_follow_up_at' => '2026-02-10',
            ]);

        $upcoming = Task::factory()
            ->for($user)
            ->create([
                'name' => 'FollowLaterTask',
                'next_follow_up_at' => '2026-02-12',
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'FollowWithoutDateTask',
                'next_follow_up_at' => null,
            ]);

        $dueResponse = $this->actingAs($user)->get(
            route('tasks.index', ['follow_up' => 'due']),
        );

        $upcomingResponse = $this->actingAs($user)->get(
            route('tasks.index', ['follow_up' => 'upcoming']),
        );

        $dueResponse
            ->assertOk()
            ->assertSee('FollowDuePastTask')
            ->assertSee('FollowDueTodayTask')
            ->assertDontSee('FollowLaterTask')
            ->assertDontSee('FollowWithoutDateTask');

        $upcomingResponse
            ->assertOk()
            ->assertSee('FollowLaterTask')
            ->assertDontSee('FollowDuePastTask')
            ->assertDontSee('FollowDueTodayTask')
            ->assertDontSee('FollowWithoutDateTask');

        expect($duePast->fresh())
            ->not->toBeNull()
            ->and($dueToday->fresh())
            ->not->toBeNull()
            ->and($upcoming->fresh())
            ->not->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

test(
    'index supports sorting by whitelisted fields and falls back on invalid sort input',
    function () {
        $user = User::factory()->create();

        $beta = Task::factory()
            ->for($user)
            ->create([
                'name' => 'Beta Task',
                'updated_at' => Carbon::parse('2026-01-01 10:00:00'),
            ]);

        $alpha = Task::factory()
            ->for($user)
            ->create([
                'name' => 'Alpha Task',
                'updated_at' => Carbon::parse('2026-01-02 10:00:00'),
            ]);

        $sortedResponse = $this->actingAs($user)->get(
            route('tasks.index', [
                'sort' => 'name',
                'direction' => 'asc',
            ]),
        );

        $fallbackResponse = $this->actingAs($user)->get(
            route('tasks.index', [
                'sort' => 'not-allowed',
                'direction' => 'sideways',
            ]),
        );

        assertTaskAppearsBefore(
            $sortedResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        assertTaskAppearsBefore(
            $fallbackResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        $fallbackResponse
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending');
    },
);

test(
    'index sorting by next follow-up keeps nulls last for both directions',
    function () {
        $user = User::factory()->create();

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Soon',
                'next_follow_up_at' => '2026-02-11',
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'Later',
                'next_follow_up_at' => '2026-02-18',
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'name' => 'No Date',
                'next_follow_up_at' => null,
            ]);

        $ascResponse = $this->actingAs($user)->get(
            route('tasks.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'asc',
            ]),
        );

        $descResponse = $this->actingAs($user)->get(
            route('tasks.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'desc',
            ]),
        );

        assertTaskAppearsBefore($ascResponse->getContent(), 'Soon', 'Later');
        assertTaskAppearsBefore($ascResponse->getContent(), 'Later', 'No Date');

        assertTaskAppearsBefore($descResponse->getContent(), 'Later', 'Soon');
        assertTaskAppearsBefore($descResponse->getContent(), 'Soon', 'No Date');
    },
);

test('store sanitizes and normalizes incoming payload', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();
    $contact = Contact::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    $response = $this->actingAs($user)->post(
        route('tasks.store'),
        taskPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => '  <b>Quarterly Review</b>  ',
            'type' => 'EMAIL',
            'status' => 'COMPLETED',
            'source' => '  <i>Inbound</i>  ',
            'outcome' => '  <script>alert(1)</script> Deal closed  ',
            'notes' => "<script>bad()</script>  Important task\r\nSecond line  ",
            'is_active' => '0',
            'next_follow_up_at' => null,
        ]),
    );

    $task = Task::query()->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('status', 'Task created successfully.');

    expect($task->user_id)
        ->toBe($user->id)
        ->and($task->company_id)
        ->toBe($company->id)
        ->and($task->contact_id)
        ->toBe($contact->id)
        ->and($task->name)
        ->toBe('Quarterly Review')
        ->and($task->type)
        ->toBe('email')
        ->and($task->status)
        ->toBe('completed')
        ->and($task->source)
        ->toBe('Inbound')
        ->and($task->outcome)
        ->toBe('alert(1) Deal closed')
        ->and($task->notes)
        ->toContain('Important task')
        ->and($task->notes)
        ->toContain('Second line')
        ->and($task->notes)
        ->not->toContain('<script>')
        ->and($task->is_active)
        ->toBeFalse();
});

test('tasks routes use dedicated read/write throttle middleware', function () {
    $indexRoute = app('router')->getRoutes()->getByName('tasks.index');
    $createRoute = app('router')->getRoutes()->getByName('tasks.create');
    $showRoute = app('router')->getRoutes()->getByName('tasks.show');
    $editRoute = app('router')->getRoutes()->getByName('tasks.edit');
    $storeRoute = app('router')->getRoutes()->getByName('tasks.store');
    $updateRoute = app('router')->getRoutes()->getByName('tasks.update');
    $destroyRoute = app('router')->getRoutes()->getByName('tasks.destroy');

    expect($indexRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-read')
        ->and($createRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-read')
        ->and($showRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-read')
        ->and($editRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-read')
        ->and($storeRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-write')
        ->and($updateRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-write')
        ->and($destroyRoute?->gatherMiddleware())
        ->toContain('throttle:tasks-write')
        ->and($storeRoute?->gatherMiddleware())
        ->not->toContain('throttle:tasks-read')
        ->and($indexRoute?->gatherMiddleware())
        ->not->toContain('throttle:tasks-write');
});

test(
    'authenticated users can render the create task page with metadata',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create(['name' => 'Acme Company']);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Acme Contact',
                'company_id' => $company->id,
            ]);

        $response = $this->actingAs($user)->get(route('tasks.create'));

        $response
            ->assertOk()
            ->assertSee('Create Task')
            ->assertSee('Task Context')
            ->assertSee('Scheduling &amp; Notes', false)
            ->assertSee('Company (optional)')
            ->assertSee('Contact (optional)')
            ->assertSee('Acme Company')
            ->assertSee('Acme Contact')
            ->assertSee('Planned')
            ->assertSee('Completed')
            ->assertSee('Canceled')
            ->assertSee('Call')
            ->assertSee('Email')
            ->assertSee('Meeting')
            ->assertSee('Note');
    },
);

test(
    'users can create tasks and data is always assigned to the authenticated user',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();
        $contact = Contact::factory()
            ->for($user)
            ->create(['company_id' => $company->id]);

        $payload = taskPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Intro Call',
            'type' => 'call',
            'status' => 'planned',
            'is_active' => '1',
        ]);

        $response = $this->actingAs($user)->post(
            route('tasks.store'),
            $payload,
        );

        $task = Task::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHas('status', 'Task created successfully.');

        expect($task->user_id)
            ->toBe($user->id)
            ->and($task->company_id)
            ->toBe($company->id)
            ->and($task->contact_id)
            ->toBe($contact->id)
            ->and($task->name)
            ->toBe('Intro Call')
            ->and($task->status)
            ->toBe('planned')
            ->and($task->is_active)
            ->toBeTrue()
            ->and(Task::query()->count())
            ->toBe(1);
    },
);

test('task creation rejects user_id injection attempts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('tasks.store'),
            taskPayload([
                'name' => 'Injection Task',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect(Task::query()->where('name', 'Injection Task')->exists())
        ->toBeFalse()
        ->and(Task::query()->count())
        ->toBe(0);
});

test('task creation validates required and constrained fields', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUsersCompany = Company::factory()->for($otherUser)->create();
    $otherUsersContact = Contact::factory()
        ->for($otherUser)
        ->create(['company_id' => $otherUsersCompany->id]);

    $response = $this->actingAs($user)->post(
        route('tasks.store'),
        taskPayload([
            'name' => '',
            'type' => 'invalid-type',
            'status' => 'invalid-status',
            'task_at' => '',
            'company_id' => $otherUsersCompany->id,
            'contact_id' => $otherUsersContact->id,
            'is_active' => 'maybe',
        ]),
    );

    $response->assertSessionHasErrors([
        'name',
        'type',
        'status',
        'task_at',
        'company_id',
        'contact_id',
        'is_active',
    ]);

    expect(Task::query()->count())->toBe(0);
});

test(
    'task name must be unique per user but may be reused across users',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Task::factory()
            ->for($user)
            ->create(['name' => 'Shared Name']);

        $this->actingAs($user)
            ->post(route('tasks.store'), taskPayload(['name' => 'Shared Name']))
            ->assertSessionHasErrors(['name']);

        $this->actingAs($otherUser)
            ->post(route('tasks.store'), taskPayload(['name' => 'Shared Name']))
            ->assertSessionHasNoErrors();

        expect(Task::query()->where('name', 'Shared Name')->count())
            ->toBe(2)
            ->and(
                Task::query()
                    ->where('user_id', $user->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1)
            ->and(
                Task::query()
                    ->where('user_id', $otherUser->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1);
    },
);

test('owners can view and edit their task records', function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create(['name' => 'Owner Company']);

    $contact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $company->id,
            'name' => 'Owner Contact',
        ]);

    $task = Task::factory()
        ->for($user)
        ->create([
            'name' => 'Owner Task',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'type' => 'meeting',
            'status' => 'completed',
            'is_active' => false,
        ]);

    $showResponse = $this->actingAs($user)->get(route('tasks.show', $task));

    $showResponse
        ->assertOk()
        ->assertSee('Owner Task')
        ->assertSee('Owner Company')
        ->assertSee('Owner Contact')
        ->assertSee('Inactive')
        ->assertSee('Completed')
        ->assertSee('Task Details')
        ->assertSee('Record Context');

    $editResponse = $this->actingAs($user)->get(route('tasks.edit', $task));

    $editResponse
        ->assertOk()
        ->assertSee('Edit Task')
        ->assertSee('Owner Task')
        ->assertSee('Save Changes')
        ->assertSee('Planned')
        ->assertSee('Completed')
        ->assertSee('Call')
        ->assertSee('Meeting');
});

test('non-owners cannot view or edit another users task', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $task = Task::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('tasks.show', $task))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('tasks.edit', $task))
        ->assertNotFound();
});

test('owners can update their tasks with sanitized values', function () {
    $user = User::factory()->create();

    $companyA = Company::factory()->for($user)->create();
    $companyB = Company::factory()->for($user)->create();

    $contactA = Contact::factory()
        ->for($user)
        ->create(['company_id' => $companyA->id]);

    $contactB = Contact::factory()
        ->for($user)
        ->create(['company_id' => $companyB->id]);

    $task = Task::factory()
        ->for($user)
        ->create([
            'company_id' => $companyA->id,
            'contact_id' => $contactA->id,
            'name' => 'Before Update',
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Outbound',
            'is_active' => true,
            'outcome' => 'Pending follow-up',
            'notes' => 'Original note',
        ]);

    $payload = taskPayload([
        'company_id' => $companyB->id,
        'contact_id' => $contactB->id,
        'name' => '  <b>After Update</b>  ',
        'type' => 'EMAIL',
        'status' => 'COMPLETED',
        'source' => '  <i>Inbound</i>  ',
        'outcome' => '  <script>alert(1)</script> Completed successfully  ',
        'notes' => "<script>bad()</script> Updated note\r\nLine two",
        'is_active' => '0',
        'next_follow_up_at' => null,
    ]);

    $response = $this->actingAs($user)->put(
        route('tasks.update', $task),
        $payload,
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('status', 'Task updated successfully.');

    $task->refresh();

    expect($task->company_id)
        ->toBe($companyB->id)
        ->and($task->contact_id)
        ->toBe($contactB->id)
        ->and($task->name)
        ->toBe('After Update')
        ->and($task->type)
        ->toBe('email')
        ->and($task->status)
        ->toBe('completed')
        ->and($task->source)
        ->toBe('Inbound')
        ->and($task->outcome)
        ->toBe('alert(1) Completed successfully')
        ->and($task->notes)
        ->toContain('Updated note')
        ->and($task->notes)
        ->toContain('Line two')
        ->and($task->notes)
        ->not->toContain('<script>')
        ->and($task->is_active)
        ->toBeFalse()
        ->and($task->user_id)
        ->toBe($user->id);
});

test(
    'update validates uniqueness while allowing unchanged names and cross-user reuse',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $existing = Task::factory()
            ->for($user)
            ->create(['name' => 'Existing Name']);

        $target = Task::factory()
            ->for($user)
            ->create(['name' => 'Target Name']);

        Task::factory()
            ->for($otherUser)
            ->create(['name' => 'Shared Elsewhere']);

        $this->actingAs($user)
            ->put(
                route('tasks.update', $target),
                taskPayload(['name' => 'Existing Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($user)
            ->put(
                route('tasks.update', $target),
                taskPayload(['name' => 'Shared Elsewhere']),
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(
                route('tasks.update', $target),
                taskPayload(['name' => 'Target Name']),
            )
            ->assertSessionHasNoErrors();

        expect($existing->refresh()->name)
            ->toBe('Existing Name')
            ->and($target->refresh()->name)
            ->toBe('Target Name');
    },
);

test('owners cannot change task ownership during update', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $task = Task::factory()
        ->for($owner)
        ->create(['name' => 'Ownership Locked']);

    $this->actingAs($owner)
        ->put(
            route('tasks.update', $task),
            taskPayload([
                'name' => 'Ownership Locked',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect($task->fresh()->user_id)
        ->toBe($owner->id)
        ->and($task->fresh()->name)
        ->toBe('Ownership Locked');
});

test('non-owners cannot update another users task', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $task = Task::factory()
        ->for($owner)
        ->create(['name' => 'Protected Task']);

    $this->actingAs($intruder)
        ->put(
            route('tasks.update', $task),
            taskPayload([
                'name' => 'Hacked Task',
            ]),
        )
        ->assertNotFound();

    expect($task->fresh()->name)->toBe('Protected Task');
});

test('owners can delete their tasks', function () {
    $user = User::factory()->create();

    $task = Task::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('tasks.destroy', $task))
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHas('status', 'Task deleted successfully.');

    $this->assertDatabaseMissing('tasks', [
        'id' => $task->id,
    ]);

    expect(Task::query()->count())->toBe(0);
});

test('non-owners cannot delete another users task', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $task = Task::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('tasks.destroy', $task))
        ->assertNotFound();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'user_id' => $owner->id,
    ]);
});

test('index shows an empty-state message when there are no tasks', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('tasks.index'));

    $response
        ->assertOk()
        ->assertSee('No tasks found with the current search/filter settings.')
        ->assertSee('Create your first task')
        ->assertSee('Rows: 15');
});

test('completed tasks must be inactive', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('tasks.store'),
            taskPayload([
                'status' => 'completed',
                'is_active' => '1',
                'next_follow_up_at' => null,
            ]),
        )
        ->assertSessionHasErrors(['is_active']);

    expect(Task::query()->count())->toBe(0);
});

test('completed or canceled tasks cannot keep a follow-up date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('tasks.store'),
            taskPayload([
                'status' => 'completed',
                'is_active' => '0',
                'next_follow_up_at' => '2026-02-05',
            ]),
        )
        ->assertSessionHasErrors(['next_follow_up_at']);

    expect(Task::query()->count())->toBe(0);
});

test(
    'task relationships must stay consistent across company contact and activity',
    function () {
        $user = User::factory()->create();

        $companyA = Company::factory()->for($user)->create();
        $companyB = Company::factory()->for($user)->create();

        $contactA = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $companyA->id,
            ]);

        $contactB = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $companyB->id,
            ]);

        $activityB = Activity::factory()
            ->for($user)
            ->create([
                'company_id' => $companyB->id,
                'contact_id' => $contactB->id,
            ]);

        $this->actingAs($user)
            ->post(
                route('tasks.store'),
                taskPayload([
                    'company_id' => $companyA->id,
                    'contact_id' => $contactB->id,
                    'activity_id' => $activityB->id,
                    'next_follow_up_at' => null,
                ]),
            )
            ->assertSessionHasErrors(['contact_id', 'activity_id']);

        $response = $this->actingAs($user)->post(
            route('tasks.store'),
            taskPayload([
                'company_id' => $companyA->id,
                'contact_id' => $contactA->id,
                'activity_id' => null,
                'next_follow_up_at' => '2026-02-05',
            ]),
        );

        $task = Task::query()->latest('id')->first();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', $task));

        expect($task)
            ->not->toBeNull()
            ->and($task->company_id)
            ->toBe($companyA->id)
            ->and($task->contact_id)
            ->toBe($contactA->id);
    },
);
