<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Carbon;

function contactPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->name(),
            'job_title' => 'Account Executive',
            'status' => 'lead',
            'department' => 'Sales',
            'source' => 'Inbound',
            'email' => 'hello@example.com',
            'alternate_email' => 'alt@example.com',
            'phone' => '+1-555-0100',
            'mobile_phone' => '+1-555-0111',
            'linkedin_url' => 'https://linkedin.com/in/example-person',
            'timezone' => 'UTC',
            'preferred_contact_method' => 'email',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 400',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'United States',
            'birthday' => '1990-04-18',
            'last_contacted_at' => '2026-01-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => '1',
            'notes' => 'Strategic contact for multi-year opportunity.',
        ],
        $overrides,
    );
}

function assertContactAppearsBefore(
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

test('guests are redirected from all contact routes', function () {
    $contact = Contact::factory()->create();

    $this->get(route('contacts.index'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('contacts.create'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->post(route('contacts.store'), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('contacts.show', $contact))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('contacts.edit', $contact))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->put(route('contacts.update', $contact), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

test(
    'index lists only authenticated users contacts and returns default view metadata',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create(['name' => 'Visible Co']);
        $otherCompany = Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Hidden Co']);

        $visible = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Visible Person',
                'company_id' => $company->id,
            ]);

        $hidden = Contact::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Hidden Person',
                'company_id' => $otherCompany->id,
            ]);

        $response = $this->actingAs($user)->get(route('contacts.index'));

        $response
            ->assertOk()
            ->assertSee('Visible Person')
            ->assertDontSee('Hidden Person');

        $response
            ->assertSee('Status: All')
            ->assertSee('Account: All')
            ->assertSee('Company: All companies')
            ->assertSee('Follow-up: All')
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending')
            ->assertSee('Rows: 15')
            ->assertSee('Lead')
            ->assertSee('Prospect')
            ->assertSee('Customer')
            ->assertSee('Churned');

        expect(Contact::query()->ownedBy($user)->pluck('id')->all())
            ->toContain($visible->id)
            ->not->toContain($hidden->id);
    },
);

test(
    'index sanitizes search input and whitelists invalid filters',
    function () {
        $user = User::factory()->create();

        Contact::factory()->count(2)->for($user)->create();

        $response = $this->actingAs($user)->get(
            route('contacts.index', [
                'search' => '  <script>alert(1)</script>   Alpha   Beta  ',
                'status' => 'drop-table',
                'active' => 'sometimes',
                'company' => 'drop-table',
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
            ->assertSee('Account: All')
            ->assertSee('Company: All companies')
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

        Contact::factory()
            ->for($user)
            ->create(['name' => 'Orion Person']);

        Contact::factory()
            ->for($user)
            ->create(['name' => 'Northwind Person']);

        Contact::factory()
            ->for($otherUser)
            ->create(['name' => 'Orion Hidden']);

        $response = $this->actingAs($user)->get(
            route('contacts.index', ['search' => 'Orion']),
        );

        $response
            ->assertOk()
            ->assertSee('Orion Person')
            ->assertDontSee('Northwind Person')
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
        $otherCompany = Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Aurora Corp']);

        $matching = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Systems',
                'department' => 'Retail',
                'city' => 'Austin',
                'status' => 'lead',
                'source' => 'Inbound',
                'country' => 'Japan',
                'company_id' => $usersCompany->id,
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Partial',
                'department' => 'Retail',
                'city' => 'Austin',
                'status' => 'lead',
                'source' => 'Inbound',
                'country' => 'Brazil',
                'company_id' => $usersCompany->id,
            ]);

        Contact::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Aurora Hidden',
                'department' => 'Retail',
                'city' => 'Austin',
                'status' => 'lead',
                'source' => 'Inbound',
                'country' => 'Japan',
                'company_id' => $otherCompany->id,
            ]);

        $search =
            'Aurora Retail Austin lead Inbound Japan seventh-term-ignored';

        $response = $this->actingAs($user)->get(
            route('contacts.index', ['search' => $search]),
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
    'index paginates contact records with default and custom per-page values',
    function () {
        $user = User::factory()->create();

        foreach (range(1, 32) as $index) {
            Contact::factory()
                ->for($user)
                ->create([
                    'name' => "Pagination Contact {$index}",
                    'updated_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                    'created_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                ]);
        }

        $defaultResponse = $this->actingAs($user)->get(route('contacts.index'));
        $customResponse = $this->actingAs($user)->get(
            route('contacts.index', ['per_page' => 25]),
        );
        $invalidResponse = $this->actingAs($user)->get(
            route('contacts.index', ['per_page' => 999]),
        );

        $defaultResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertSee('Pagination Contact 32')
            ->assertSee('Pagination Contact 18')
            ->assertDontSee('Pagination Contact 17');

        $customResponse
            ->assertOk()
            ->assertSee('Rows: 25')
            ->assertSee('Pagination Contact 17')
            ->assertDontSee('Pagination Contact 7');

        $invalidResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertDontSee('Pagination Contact 17');
    },
);

test(
    'index filters by status, activity, company, and follow-up date',
    function () {
        $user = User::factory()->create();

        $matchingCompany = Company::factory()
            ->for($user)
            ->create(['name' => 'Match Company']);
        $otherCompany = Company::factory()
            ->for($user)
            ->create(['name' => 'Other Company']);

        $matching = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Match Contact',
                'status' => 'customer',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'next_follow_up_at' => null,
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Status',
                'status' => 'lead',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'next_follow_up_at' => null,
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Activity',
                'status' => 'customer',
                'is_active' => true,
                'company_id' => $matchingCompany->id,
                'next_follow_up_at' => null,
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Company',
                'status' => 'customer',
                'is_active' => false,
                'company_id' => $otherCompany->id,
                'next_follow_up_at' => null,
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Has Follow Up',
                'status' => 'customer',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'next_follow_up_at' => now()->addDay(),
            ]);

        $response = $this->actingAs($user)->get(
            route('contacts.index', [
                'status' => 'customer',
                'active' => 'inactive',
                'company' => (string) $matchingCompany->id,
                'follow_up' => 'none',
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('Match Contact')
            ->assertDontSee('Wrong Status')
            ->assertDontSee('Wrong Activity')
            ->assertDontSee('Wrong Company')
            ->assertDontSee('Has Follow Up');

        $response
            ->assertSee('Status: Customer')
            ->assertSee('Account: Inactive')
            ->assertSee('Company: Match Company')
            ->assertSee('Follow-up: No date');

        expect($matching->fresh())
            ->not->toBeNull()
            ->and($matching->fresh()->name)
            ->toBe('Match Contact');
    },
);

test('index follow-up filter supports due and upcoming buckets', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-10 09:00:00'));

    try {
        $user = User::factory()->create();

        $duePast = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDuePastContact',
                'next_follow_up_at' => '2026-02-09',
            ]);

        $dueToday = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDueTodayContact',
                'next_follow_up_at' => '2026-02-10',
            ]);

        $upcoming = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'FollowLaterContact',
                'next_follow_up_at' => '2026-02-12',
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'FollowWithoutDateContact',
                'next_follow_up_at' => null,
            ]);

        $dueResponse = $this->actingAs($user)->get(
            route('contacts.index', ['follow_up' => 'due']),
        );

        $upcomingResponse = $this->actingAs($user)->get(
            route('contacts.index', ['follow_up' => 'upcoming']),
        );

        $dueResponse
            ->assertOk()
            ->assertSee('FollowDuePastContact')
            ->assertSee('FollowDueTodayContact')
            ->assertDontSee('FollowLaterContact')
            ->assertDontSee('FollowWithoutDateContact');

        $upcomingResponse
            ->assertOk()
            ->assertSee('FollowLaterContact')
            ->assertDontSee('FollowDuePastContact')
            ->assertDontSee('FollowDueTodayContact')
            ->assertDontSee('FollowWithoutDateContact');

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

        $beta = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Beta Contact',
                'updated_at' => Carbon::parse('2026-01-01 10:00:00'),
            ]);

        $alpha = Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Alpha Contact',
                'updated_at' => Carbon::parse('2026-01-02 10:00:00'),
            ]);

        $sortedResponse = $this->actingAs($user)->get(
            route('contacts.index', [
                'sort' => 'name',
                'direction' => 'asc',
            ]),
        );

        $fallbackResponse = $this->actingAs($user)->get(
            route('contacts.index', [
                'sort' => 'not-allowed',
                'direction' => 'sideways',
            ]),
        );

        assertContactAppearsBefore(
            $sortedResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        assertContactAppearsBefore(
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

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Soon',
                'next_follow_up_at' => '2026-02-11',
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'Later',
                'next_follow_up_at' => '2026-02-18',
            ]);

        Contact::factory()
            ->for($user)
            ->create([
                'name' => 'No Date',
                'next_follow_up_at' => null,
            ]);

        $ascResponse = $this->actingAs($user)->get(
            route('contacts.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'asc',
            ]),
        );

        $descResponse = $this->actingAs($user)->get(
            route('contacts.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'desc',
            ]),
        );

        assertContactAppearsBefore($ascResponse->getContent(), 'Soon', 'Later');
        assertContactAppearsBefore(
            $ascResponse->getContent(),
            'Later',
            'No Date',
        );

        assertContactAppearsBefore(
            $descResponse->getContent(),
            'Later',
            'Soon',
        );
        assertContactAppearsBefore(
            $descResponse->getContent(),
            'Soon',
            'No Date',
        );
    },
);

test('store sanitizes and normalizes incoming payload', function () {
    $user = User::factory()->create();
    $company = Company::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(
        route('contacts.store'),
        contactPayload([
            'company_id' => $company->id,
            'name' => '  <b>Alex Sanitized</b>  ',
            'status' => 'LEAD',
            'email' => '  SALES@EXAMPLE.COM ',
            'alternate_email' => ' ALT@EXAMPLE.COM ',
            'linkedin_url' => 'linkedin.com/in/alex',
            'preferred_contact_method' => 'EMAIL',
            'notes' => "<script>alert(1)</script>  Important contact\r\nSecond line  ",
        ]),
    );

    $contact = Contact::query()->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('contacts.show', $contact))
        ->assertSessionHas('status', 'Contact created successfully.');

    expect($contact->user_id)
        ->toBe($user->id)
        ->and($contact->company_id)
        ->toBe($company->id)
        ->and($contact->name)
        ->toBe('Alex Sanitized')
        ->and($contact->status)
        ->toBe('lead')
        ->and($contact->email)
        ->toBe('sales@example.com')
        ->and($contact->alternate_email)
        ->toBe('alt@example.com')
        ->and($contact->linkedin_url)
        ->toBe('https://linkedin.com/in/alex')
        ->and($contact->preferred_contact_method)
        ->toBe('email')
        ->and($contact->notes)
        ->toContain('Important contact')
        ->and($contact->notes)
        ->toContain('Second line')
        ->and($contact->notes)
        ->not->toContain('<script>');
});

test(
    'contacts routes use dedicated read/write throttle middleware',
    function () {
        $indexRoute = app('router')->getRoutes()->getByName('contacts.index');
        $createRoute = app('router')->getRoutes()->getByName('contacts.create');
        $showRoute = app('router')->getRoutes()->getByName('contacts.show');
        $editRoute = app('router')->getRoutes()->getByName('contacts.edit');
        $storeRoute = app('router')->getRoutes()->getByName('contacts.store');
        $updateRoute = app('router')->getRoutes()->getByName('contacts.update');
        $destroyRoute = app('router')
            ->getRoutes()
            ->getByName('contacts.destroy');

        expect($indexRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-read')
            ->and($createRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-read')
            ->and($showRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-read')
            ->and($editRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-read')
            ->and($storeRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-write')
            ->and($updateRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-write')
            ->and($destroyRoute?->gatherMiddleware())
            ->toContain('throttle:contacts-write')
            ->and($storeRoute?->gatherMiddleware())
            ->not->toContain('throttle:contacts-read')
            ->and($indexRoute?->gatherMiddleware())
            ->not->toContain('throttle:contacts-write');
    },
);

test(
    'authenticated users can render the create contact page with metadata',
    function () {
        $user = User::factory()->create();
        Company::factory()
            ->for($user)
            ->create(['name' => 'Acme Company']);

        $response = $this->actingAs($user)->get(route('contacts.create'));

        $response
            ->assertOk()
            ->assertSee('Create Contact')
            ->assertSee('Contact Profile')
            ->assertSee('Company (optional)')
            ->assertSee('Acme Company')
            ->assertSee('Preferred contact method')
            ->assertSee('Lead')
            ->assertSee('Prospect')
            ->assertSee('Customer')
            ->assertSee('Churned')
            ->assertSee('Email')
            ->assertSee('Phone')
            ->assertSee('Linkedin')
            ->assertSee('Any');
    },
);

test(
    'users can create contacts and data is always assigned to the authenticated user',
    function () {
        $user = User::factory()->create();
        $company = Company::factory()->for($user)->create();

        $payload = contactPayload([
            'company_id' => $company->id,
            'name' => 'Alex Carter',
            'status' => 'lead',
            'is_active' => '1',
        ]);

        $response = $this->actingAs($user)->post(
            route('contacts.store'),
            $payload,
        );

        $contact = Contact::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contacts.show', $contact))
            ->assertSessionHas('status', 'Contact created successfully.');

        expect($contact->user_id)
            ->toBe($user->id)
            ->and($contact->company_id)
            ->toBe($company->id)
            ->and($contact->name)
            ->toBe('Alex Carter')
            ->and($contact->status)
            ->toBe('lead')
            ->and($contact->is_active)
            ->toBeTrue()
            ->and(Contact::query()->count())
            ->toBe(1);
    },
);

test('contact creation rejects user_id injection attempts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('contacts.store'),
            contactPayload([
                'name' => 'Injection Contact',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect(Contact::query()->where('name', 'Injection Contact')->exists())
        ->toBeFalse()
        ->and(Contact::query()->count())
        ->toBe(0);
});

test('contact creation validates required and constrained fields', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUsersCompany = Company::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->post(
        route('contacts.store'),
        contactPayload([
            'name' => '',
            'status' => 'invalid-status',
            'email' => 'invalid-email',
            'linkedin_url' => 'not-a-url',
            'phone' => 'abc',
            'mobile_phone' => 'abc',
            'birthday' => now()->addDay()->toDateString(),
            'next_follow_up_at' => '2026-01-01',
            'last_contacted_at' => '2026-02-01',
            'company_id' => $otherUsersCompany->id,
            'is_active' => 'maybe',
        ]),
    );

    $response->assertSessionHasErrors([
        'name',
        'status',
        'email',
        'linkedin_url',
        'phone',
        'mobile_phone',
        'birthday',
        'next_follow_up_at',
        'company_id',
        'is_active',
    ]);

    expect(Contact::query()->count())->toBe(0);
});

test('contact creation rejects deceptive linkedin domains', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('contacts.store'),
            contactPayload([
                'linkedin_url' => 'https://evil-linkedin.com/in/fake',
            ]),
        )
        ->assertSessionHasErrors(['linkedin_url']);

    expect(Contact::query()->count())->toBe(0);
});

test(
    'contact name must be unique per user but may be reused across users',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Contact::factory()
            ->for($user)
            ->create(['name' => 'Shared Name']);

        $this->actingAs($user)
            ->post(
                route('contacts.store'),
                contactPayload(['name' => 'Shared Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($otherUser)
            ->post(
                route('contacts.store'),
                contactPayload(['name' => 'Shared Name']),
            )
            ->assertSessionHasNoErrors();

        expect(Contact::query()->where('name', 'Shared Name')->count())
            ->toBe(2)
            ->and(
                Contact::query()
                    ->where('user_id', $user->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1)
            ->and(
                Contact::query()
                    ->where('user_id', $otherUser->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1);
    },
);

test('owners can view and edit their contact records', function () {
    $user = User::factory()->create();
    $company = Company::factory()
        ->for($user)
        ->create(['name' => 'Owner Company']);

    $contact = Contact::factory()
        ->for($user)
        ->create([
            'name' => 'Owner Contact',
            'company_id' => $company->id,
            'job_title' => 'VP Finance',
            'status' => 'customer',
            'is_active' => false,
        ]);

    $showResponse = $this->actingAs($user)->get(
        route('contacts.show', $contact),
    );

    $showResponse
        ->assertOk()
        ->assertSee('Owner Contact')
        ->assertSee('Owner Company')
        ->assertSee('VP Finance')
        ->assertSee('Inactive')
        ->assertSee('Customer')
        ->assertSee('Contact Information')
        ->assertSee('Communication Information');

    $editResponse = $this->actingAs($user)->get(
        route('contacts.edit', $contact),
    );

    $editResponse
        ->assertOk()
        ->assertSee('Edit Contact')
        ->assertSee('Owner Contact')
        ->assertSee('Save Changes')
        ->assertSee('Prospect')
        ->assertSee('Customer')
        ->assertSee('Linkedin')
        ->assertSee('Any');
});

test('non-owners cannot view or edit another users contact', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $contact = Contact::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('contacts.show', $contact))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('contacts.edit', $contact))
        ->assertNotFound();
});

test('owners can update their contacts with sanitized values', function () {
    $user = User::factory()->create();
    $companyA = Company::factory()->for($user)->create();
    $companyB = Company::factory()->for($user)->create();

    $contact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $companyA->id,
            'name' => 'Before Update',
            'status' => 'lead',
            'email' => 'before@example.com',
            'is_active' => true,
            'notes' => 'Original note',
        ]);

    $payload = contactPayload([
        'company_id' => $companyB->id,
        'name' => '  <b>After Update</b>  ',
        'status' => 'CUSTOMER',
        'email' => '  AFTER@EXAMPLE.COM ',
        'alternate_email' => ' ALT+UPDATED@EXAMPLE.COM ',
        'linkedin_url' => 'linkedin.com/in/after-update',
        'preferred_contact_method' => 'PHONE',
        'notes' => "<script>bad()</script> Updated note\r\nLine two",
        'is_active' => '0',
    ]);

    $response = $this->actingAs($user)->put(
        route('contacts.update', $contact),
        $payload,
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('contacts.show', $contact))
        ->assertSessionHas('status', 'Contact updated successfully.');

    $contact->refresh();

    expect($contact->company_id)
        ->toBe($companyB->id)
        ->and($contact->name)
        ->toBe('After Update')
        ->and($contact->status)
        ->toBe('customer')
        ->and($contact->email)
        ->toBe('after@example.com')
        ->and($contact->alternate_email)
        ->toBe('alt+updated@example.com')
        ->and($contact->linkedin_url)
        ->toBe('https://linkedin.com/in/after-update')
        ->and($contact->preferred_contact_method)
        ->toBe('phone')
        ->and($contact->notes)
        ->toContain('Updated note')
        ->and($contact->notes)
        ->toContain('Line two')
        ->and($contact->notes)
        ->not->toContain('<script>')
        ->and($contact->is_active)
        ->toBeFalse()
        ->and($contact->user_id)
        ->toBe($user->id);
});

test(
    'update validates uniqueness while allowing unchanged names and cross-user reuse',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $existing = Contact::factory()
            ->for($user)
            ->create(['name' => 'Existing Name']);

        $target = Contact::factory()
            ->for($user)
            ->create(['name' => 'Target Name']);

        Contact::factory()
            ->for($otherUser)
            ->create(['name' => 'Shared Elsewhere']);

        $this->actingAs($user)
            ->put(
                route('contacts.update', $target),
                contactPayload(['name' => 'Existing Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($user)
            ->put(
                route('contacts.update', $target),
                contactPayload(['name' => 'Shared Elsewhere']),
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(
                route('contacts.update', $target),
                contactPayload(['name' => 'Target Name']),
            )
            ->assertSessionHasNoErrors();

        expect($existing->refresh()->name)
            ->toBe('Existing Name')
            ->and($target->refresh()->name)
            ->toBe('Target Name');
    },
);

test('owners cannot change contact ownership during update', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $contact = Contact::factory()
        ->for($owner)
        ->create(['name' => 'Ownership Locked']);

    $this->actingAs($owner)
        ->put(
            route('contacts.update', $contact),
            contactPayload([
                'name' => 'Ownership Locked',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect($contact->fresh()->user_id)
        ->toBe($owner->id)
        ->and($contact->fresh()->name)
        ->toBe('Ownership Locked');
});

test('non-owners cannot update another users contact', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $contact = Contact::factory()
        ->for($owner)
        ->create(['name' => 'Protected Contact']);

    $this->actingAs($intruder)
        ->put(
            route('contacts.update', $contact),
            contactPayload([
                'name' => 'Hacked Contact',
            ]),
        )
        ->assertNotFound();

    expect($contact->fresh()->name)->toBe('Protected Contact');
});

test('owners can delete their contacts', function () {
    $user = User::factory()->create();

    $contact = Contact::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'))
        ->assertSessionHas('status', 'Contact deleted successfully.');

    $this->assertDatabaseMissing('contacts', [
        'id' => $contact->id,
    ]);

    expect(Contact::query()->count())->toBe(0);
});

test('non-owners cannot delete another users contact', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $contact = Contact::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('contacts.destroy', $contact))
        ->assertNotFound();

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'user_id' => $owner->id,
    ]);
});

test(
    'index shows an empty-state message when there are no contacts',
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('contacts.index'));

        $response
            ->assertOk()
            ->assertSee(
                'No contacts found with the current search/filter settings.',
            )
            ->assertSee('Create your first contact')
            ->assertSee('Rows: 15');
    },
);
