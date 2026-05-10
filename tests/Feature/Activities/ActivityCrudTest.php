<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Carbon;

function activityPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->sentence(3),
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Inbound',
            'activity_at' => '2026-01-10',
            'notes' => 'Customer interaction notes for Q1 plan.',
        ],
        $overrides,
    );
}

function assertActivityAppearsBefore(
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

test('guests are redirected from all activity routes', function () {
    $activity = Activity::factory()->create();

    $this->get(route('activities.index'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('activities.create'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->post(route('activities.store'), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('activities.show', $activity))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('activities.edit', $activity))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->put(route('activities.update', $activity), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->delete(route('activities.destroy', $activity))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

test(
    'index lists only authenticated users activities and returns default view metadata',
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

        $visible = Activity::factory()
            ->for($user)
            ->create([
                'name' => 'Visible Activity',
                'company_id' => $company->id,
                'contact_id' => $contact->id,
            ]);

        $hidden = Activity::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Hidden Activity',
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
            ]);

        $response = $this->actingAs($user)->get(route('activities.index'));

        $response
            ->assertOk()
            ->assertSee('Visible Activity')
            ->assertDontSee('Hidden Activity');

        $response
            ->assertSee('Status: All')
            ->assertSee('Type: All')
            ->assertSee('Company: All companies')
            ->assertSee('Contact: All contacts')
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

        expect(Activity::query()->ownedBy($user)->pluck('id')->all())
            ->toContain($visible->id)
            ->not->toContain($hidden->id);
    },
);

test(
    'index sanitizes search input and whitelists invalid filters',
    function () {
        $user = User::factory()->create();

        Activity::factory()->count(2)->for($user)->create();

        $response = $this->actingAs($user)->get(
            route('activities.index', [
                'search' => '  <script>alert(1)</script>   Alpha   Beta  ',
                'status' => 'drop-table',
                'type' => 'drop-table',
                'company' => 'drop-table',
                'contact' => 'drop-table',
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
            ->assertSee('Company: All companies')
            ->assertSee('Contact: All contacts')
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

        Activity::factory()
            ->for($user)
            ->create(['name' => 'Orion Activity']);

        Activity::factory()
            ->for($user)
            ->create(['name' => 'Northwind Activity']);

        Activity::factory()
            ->for($otherUser)
            ->create(['name' => 'Orion Hidden']);

        $response = $this->actingAs($user)->get(
            route('activities.index', ['search' => 'Orion']),
        );

        $response
            ->assertOk()
            ->assertSee('Orion Activity')
            ->assertDontSee('Northwind Activity')
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

        $matching = Activity::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Systems',
                'type' => 'call',
                'status' => 'planned',
                'source' => 'Inbound',
                'notes' => 'Japan confirmed',
                'company_id' => $usersCompany->id,
                'contact_id' => $usersContact->id,
            ]);

        Activity::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Partial',
                'type' => 'call',
                'status' => 'planned',
                'source' => 'Inbound',
                'notes' => 'Brazil pending',
                'company_id' => $usersCompany->id,
                'contact_id' => $usersContact->id,
            ]);

        Activity::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Aurora Hidden',
                'type' => 'call',
                'status' => 'planned',
                'source' => 'Inbound',
                'notes' => 'Japan confirmed',
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
            ]);

        $search =
            'Aurora call planned inbound japan confirmed seventh-term-ignored';

        $response = $this->actingAs($user)->get(
            route('activities.index', ['search' => $search]),
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
    'index paginates activity records with default and custom per-page values',
    function () {
        $user = User::factory()->create();

        foreach (range(1, 32) as $index) {
            Activity::factory()
                ->for($user)
                ->create([
                    'name' => "Pagination Activity {$index}",
                    'updated_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                    'created_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                ]);
        }

        $defaultResponse = $this->actingAs($user)->get(
            route('activities.index'),
        );
        $customResponse = $this->actingAs($user)->get(
            route('activities.index', ['per_page' => 25]),
        );
        $invalidResponse = $this->actingAs($user)->get(
            route('activities.index', ['per_page' => 999]),
        );

        $defaultResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertSee('Pagination Activity 32')
            ->assertSee('Pagination Activity 18')
            ->assertDontSee('Pagination Activity 17');

        $customResponse
            ->assertOk()
            ->assertSee('Rows: 25')
            ->assertSee('Pagination Activity 17')
            ->assertDontSee('Pagination Activity 7');

        $invalidResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertDontSee('Pagination Activity 17');
    },
);

test('index filters by status, type, company, and contact', function () {
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

    $matching = Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Match Activity',
            'status' => 'completed',
            'type' => 'email',
            'company_id' => $matchingCompany->id,
            'contact_id' => $matchingContact->id,
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Wrong Status',
            'status' => 'planned',
            'type' => 'email',
            'company_id' => $matchingCompany->id,
            'contact_id' => $matchingContact->id,
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Wrong Type',
            'status' => 'completed',
            'type' => 'call',
            'company_id' => $matchingCompany->id,
            'contact_id' => $matchingContact->id,
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Wrong Company',
            'status' => 'completed',
            'type' => 'email',
            'company_id' => $otherCompany->id,
            'contact_id' => $otherContact->id,
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Wrong Contact',
            'status' => 'completed',
            'type' => 'email',
            'company_id' => $matchingCompany->id,
            'contact_id' => $otherContact->id,
        ]);

    $response = $this->actingAs($user)->get(
        route('activities.index', [
            'status' => 'completed',
            'type' => 'email',
            'company' => (string) $matchingCompany->id,
            'contact' => (string) $matchingContact->id,
        ]),
    );

    $response
        ->assertOk()
        ->assertSee('Match Activity')
        ->assertDontSee('Wrong Status')
        ->assertDontSee('Wrong Type')
        ->assertDontSee('Wrong Company')
        ->assertDontSee('Wrong Contact');

    $response
        ->assertSee('Status: Completed')
        ->assertSee('Type: Email')
        ->assertSee('Company: Match Company')
        ->assertSee('Contact: Match Contact');

    expect($matching->fresh())
        ->not->toBeNull()
        ->and($matching->fresh()->name)
        ->toBe('Match Activity');
});

test(
    'index supports sorting by whitelisted fields and falls back on invalid sort input',
    function () {
        $user = User::factory()->create();

        $beta = Activity::factory()
            ->for($user)
            ->create([
                'name' => 'Beta Activity',
                'updated_at' => Carbon::parse('2026-01-01 10:00:00'),
            ]);

        $alpha = Activity::factory()
            ->for($user)
            ->create([
                'name' => 'Alpha Activity',
                'updated_at' => Carbon::parse('2026-01-02 10:00:00'),
            ]);

        $sortedResponse = $this->actingAs($user)->get(
            route('activities.index', [
                'sort' => 'name',
                'direction' => 'asc',
            ]),
        );

        $fallbackResponse = $this->actingAs($user)->get(
            route('activities.index', [
                'sort' => 'not-allowed',
                'direction' => 'sideways',
            ]),
        );

        assertActivityAppearsBefore(
            $sortedResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        assertActivityAppearsBefore(
            $fallbackResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        $fallbackResponse
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending');
    },
);

test('index sorting by activity date works in both directions', function () {
    $user = User::factory()->create();

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Soon',
            'activity_at' => '2026-02-11',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Later',
            'activity_at' => '2026-02-18',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'name' => 'No Date',
            'activity_at' => '2026-02-01',
        ]);

    $ascResponse = $this->actingAs($user)->get(
        route('activities.index', [
            'sort' => 'activity_at',
            'direction' => 'asc',
        ]),
    );

    $descResponse = $this->actingAs($user)->get(
        route('activities.index', [
            'sort' => 'activity_at',
            'direction' => 'desc',
        ]),
    );

    assertActivityAppearsBefore($ascResponse->getContent(), 'No Date', 'Soon');
    assertActivityAppearsBefore($ascResponse->getContent(), 'Soon', 'Later');

    assertActivityAppearsBefore($descResponse->getContent(), 'Later', 'Soon');
    assertActivityAppearsBefore($descResponse->getContent(), 'Soon', 'No Date');
});

test('store sanitizes and normalizes incoming payload', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();
    $contact = Contact::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    $response = $this->actingAs($user)->post(
        route('activities.store'),
        activityPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => '  <b>Quarterly Review</b>  ',
            'type' => 'EMAIL',
            'status' => 'COMPLETED',
            'source' => '  <i>Inbound</i>  ',
            'notes' => "<script>bad()</script>  Important activity\r\nSecond line  ",
        ]),
    );

    $activity = Activity::query()->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('activities.show', $activity))
        ->assertSessionHas('status', 'Activity created successfully.');

    expect($activity->user_id)
        ->toBe($user->id)
        ->and($activity->company_id)
        ->toBe($company->id)
        ->and($activity->contact_id)
        ->toBe($contact->id)
        ->and($activity->name)
        ->toBe('Quarterly Review')
        ->and($activity->type)
        ->toBe('email')
        ->and($activity->status)
        ->toBe('completed')
        ->and($activity->source)
        ->toBe('Inbound')
        ->and($activity->notes)
        ->toContain('Important activity')
        ->and($activity->notes)
        ->toContain('Second line')
        ->and($activity->notes)
        ->not->toContain('<script>');
});

test(
    'activities routes use dedicated read/write throttle middleware',
    function () {
        $indexRoute = app('router')->getRoutes()->getByName('activities.index');
        $createRoute = app('router')
            ->getRoutes()
            ->getByName('activities.create');
        $showRoute = app('router')->getRoutes()->getByName('activities.show');
        $editRoute = app('router')->getRoutes()->getByName('activities.edit');
        $storeRoute = app('router')->getRoutes()->getByName('activities.store');
        $updateRoute = app('router')
            ->getRoutes()
            ->getByName('activities.update');
        $destroyRoute = app('router')
            ->getRoutes()
            ->getByName('activities.destroy');

        expect($indexRoute?->gatherMiddleware())
            ->toContain('throttle:activities-read')
            ->and($createRoute?->gatherMiddleware())
            ->toContain('throttle:activities-read')
            ->and($showRoute?->gatherMiddleware())
            ->toContain('throttle:activities-read')
            ->and($editRoute?->gatherMiddleware())
            ->toContain('throttle:activities-read')
            ->and($storeRoute?->gatherMiddleware())
            ->toContain('throttle:activities-write')
            ->and($updateRoute?->gatherMiddleware())
            ->toContain('throttle:activities-write')
            ->and($destroyRoute?->gatherMiddleware())
            ->toContain('throttle:activities-write')
            ->and($storeRoute?->gatherMiddleware())
            ->not->toContain('throttle:activities-read')
            ->and($indexRoute?->gatherMiddleware())
            ->not->toContain('throttle:activities-write');
    },
);

test(
    'authenticated users can render the create activity page with metadata',
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

        $response = $this->actingAs($user)->get(route('activities.create'));

        $response
            ->assertOk()
            ->assertSee('Create Activity')
            ->assertSee('Interaction Context')
            ->assertSee('Timeline &amp; Notes', false)
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
    'users can create activities and data is always assigned to the authenticated user',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();
        $contact = Contact::factory()
            ->for($user)
            ->create(['company_id' => $company->id]);

        $payload = activityPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Intro Call',
            'type' => 'call',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($user)->post(
            route('activities.store'),
            $payload,
        );

        $activity = Activity::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('activities.show', $activity))
            ->assertSessionHas('status', 'Activity created successfully.');

        expect($activity->user_id)
            ->toBe($user->id)
            ->and($activity->company_id)
            ->toBe($company->id)
            ->and($activity->contact_id)
            ->toBe($contact->id)
            ->and($activity->name)
            ->toBe('Intro Call')
            ->and($activity->status)
            ->toBe('planned')
            ->and(Activity::query()->count())
            ->toBe(1);
    },
);

test('activity creation rejects user_id injection attempts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('activities.store'),
            activityPayload([
                'name' => 'Injection Activity',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect(Activity::query()->where('name', 'Injection Activity')->exists())
        ->toBeFalse()
        ->and(Activity::query()->count())
        ->toBe(0);
});

test(
    'activity creation validates required and constrained fields',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUsersCompany = Company::factory()->for($otherUser)->create();
        $otherUsersContact = Contact::factory()
            ->for($otherUser)
            ->create(['company_id' => $otherUsersCompany->id]);

        $response = $this->actingAs($user)->post(
            route('activities.store'),
            activityPayload([
                'name' => '',
                'type' => 'invalid-type',
                'status' => 'invalid-status',
                'activity_at' => '',
                'company_id' => $otherUsersCompany->id,
                'contact_id' => $otherUsersContact->id,
            ]),
        );

        $response->assertSessionHasErrors([
            'name',
            'type',
            'status',
            'activity_at',
            'company_id',
            'contact_id',
        ]);

        expect(Activity::query()->count())->toBe(0);
    },
);

test(
    'activity name must be unique per user but may be reused across users',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Activity::factory()
            ->for($user)
            ->create(['name' => 'Shared Name']);

        $this->actingAs($user)
            ->post(
                route('activities.store'),
                activityPayload(['name' => 'Shared Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($otherUser)
            ->post(
                route('activities.store'),
                activityPayload(['name' => 'Shared Name']),
            )
            ->assertSessionHasNoErrors();

        expect(Activity::query()->where('name', 'Shared Name')->count())
            ->toBe(2)
            ->and(
                Activity::query()
                    ->where('user_id', $user->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1)
            ->and(
                Activity::query()
                    ->where('user_id', $otherUser->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1);
    },
);

test('owners can view and edit their activity records', function () {
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

    $activity = Activity::factory()
        ->for($user)
        ->create([
            'name' => 'Owner Activity',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'type' => 'meeting',
            'status' => 'completed',
        ]);

    $showResponse = $this->actingAs($user)->get(
        route('activities.show', $activity),
    );

    $showResponse
        ->assertOk()
        ->assertSee('Owner Activity')
        ->assertSee('Owner Company')
        ->assertSee('Owner Contact')
        ->assertSee('Completed')
        ->assertSee('Activity Details')
        ->assertSee('Record Context');

    $editResponse = $this->actingAs($user)->get(
        route('activities.edit', $activity),
    );

    $editResponse
        ->assertOk()
        ->assertSee('Edit Activity')
        ->assertSee('Owner Activity')
        ->assertSee('Save Changes')
        ->assertSee('Planned')
        ->assertSee('Completed')
        ->assertSee('Call')
        ->assertSee('Meeting');
});

test('non-owners cannot view or edit another users activity', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $activity = Activity::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('activities.show', $activity))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('activities.edit', $activity))
        ->assertNotFound();
});

test('owners can update their activities with sanitized values', function () {
    $user = User::factory()->create();

    $companyA = Company::factory()->for($user)->create();
    $companyB = Company::factory()->for($user)->create();

    $contactA = Contact::factory()
        ->for($user)
        ->create(['company_id' => $companyA->id]);

    $contactB = Contact::factory()
        ->for($user)
        ->create(['company_id' => $companyB->id]);

    $activity = Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $companyA->id,
            'contact_id' => $contactA->id,
            'name' => 'Before Update',
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Outbound',
            'notes' => 'Original note',
        ]);

    $payload = activityPayload([
        'company_id' => $companyB->id,
        'contact_id' => $contactB->id,
        'name' => '  <b>After Update</b>  ',
        'type' => 'EMAIL',
        'status' => 'COMPLETED',
        'source' => '  <i>Inbound</i>  ',
        'notes' => "<script>bad()</script> Updated note\r\nLine two",
    ]);

    $response = $this->actingAs($user)->put(
        route('activities.update', $activity),
        $payload,
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('activities.show', $activity))
        ->assertSessionHas('status', 'Activity updated successfully.');

    $activity->refresh();

    expect($activity->company_id)
        ->toBe($companyB->id)
        ->and($activity->contact_id)
        ->toBe($contactB->id)
        ->and($activity->name)
        ->toBe('After Update')
        ->and($activity->type)
        ->toBe('email')
        ->and($activity->status)
        ->toBe('completed')
        ->and($activity->source)
        ->toBe('Inbound')
        ->and($activity->notes)
        ->toContain('Updated note')
        ->and($activity->notes)
        ->toContain('Line two')
        ->and($activity->notes)
        ->not->toContain('<script>')
        ->and($activity->user_id)
        ->toBe($user->id);
});

test(
    'update validates uniqueness while allowing unchanged names and cross-user reuse',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $existing = Activity::factory()
            ->for($user)
            ->create(['name' => 'Existing Name']);

        $target = Activity::factory()
            ->for($user)
            ->create(['name' => 'Target Name']);

        Activity::factory()
            ->for($otherUser)
            ->create(['name' => 'Shared Elsewhere']);

        $this->actingAs($user)
            ->put(
                route('activities.update', $target),
                activityPayload(['name' => 'Existing Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($user)
            ->put(
                route('activities.update', $target),
                activityPayload(['name' => 'Shared Elsewhere']),
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(
                route('activities.update', $target),
                activityPayload(['name' => 'Target Name']),
            )
            ->assertSessionHasNoErrors();

        expect($existing->refresh()->name)
            ->toBe('Existing Name')
            ->and($target->refresh()->name)
            ->toBe('Target Name');
    },
);

test('owners cannot change activity ownership during update', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $activity = Activity::factory()
        ->for($owner)
        ->create(['name' => 'Ownership Locked']);

    $this->actingAs($owner)
        ->put(
            route('activities.update', $activity),
            activityPayload([
                'name' => 'Ownership Locked',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect($activity->fresh()->user_id)
        ->toBe($owner->id)
        ->and($activity->fresh()->name)
        ->toBe('Ownership Locked');
});

test('non-owners cannot update another users activity', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $activity = Activity::factory()
        ->for($owner)
        ->create(['name' => 'Protected Activity']);

    $this->actingAs($intruder)
        ->put(
            route('activities.update', $activity),
            activityPayload([
                'name' => 'Hacked Activity',
            ]),
        )
        ->assertNotFound();

    expect($activity->fresh()->name)->toBe('Protected Activity');
});

test('owners can delete their activities', function () {
    $user = User::factory()->create();

    $activity = Activity::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('activities.destroy', $activity))
        ->assertRedirect(route('activities.index'))
        ->assertSessionHas('status', 'Activity deleted successfully.');

    $this->assertDatabaseMissing('activities', [
        'id' => $activity->id,
    ]);

    expect(Activity::query()->count())->toBe(0);
});

test('non-owners cannot delete another users activity', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $activity = Activity::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('activities.destroy', $activity))
        ->assertNotFound();

    $this->assertDatabaseHas('activities', [
        'id' => $activity->id,
        'user_id' => $owner->id,
    ]);
});

test(
    'index shows an empty-state message when there are no activities',
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('activities.index'));

        $response
            ->assertOk()
            ->assertSee(
                'No activities found with the current search/filter settings.',
            )
            ->assertSee('Log your first activity')
            ->assertSee('Rows: 15');
    },
);
