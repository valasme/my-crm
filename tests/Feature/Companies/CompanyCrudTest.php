<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Carbon;

function companyPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->company(),
            'legal_name' => fake()->company().' LLC',
            'status' => 'lead',
            'industry' => 'Technology',
            'source' => 'Inbound',
            'ownership_type' => 'Private',
            'founded_year' => 2010,
            'employee_count' => 120,
            'annual_revenue' => 2500000.25,
            'website' => 'https://example.com',
            'linkedin_url' => 'https://linkedin.com/company/example',
            'email' => 'hello@example.com',
            'billing_email' => 'billing@example.com',
            'phone' => '+1-555-0100',
            'support_phone' => '+1-555-0111',
            'timezone' => 'UTC',
            'preferred_contact_method' => 'email',
            'tax_id' => '12-3456789',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 400',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'United States',
            'last_contacted_at' => '2026-01-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => '1',
            'notes' => 'Enterprise account with multi-year opportunity.',
        ],
        $overrides,
    );
}

function assertAppearsBefore(
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

test('guests are redirected from all company routes', function () {
    $company = Company::factory()->create();

    $this->get(route('companies.index'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('companies.create'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->post(route('companies.store'), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('companies.show', $company))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('companies.edit', $company))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->put(route('companies.update', $company), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->delete(route('companies.destroy', $company))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

test(
    'index lists only authenticated users companies and returns default view metadata',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $visible = Company::factory()
            ->for($user)
            ->create(['name' => 'Visible Co']);

        $hidden = Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Hidden Co']);

        $response = $this->actingAs($user)->get(route('companies.index'));

        $response
            ->assertOk()
            ->assertSee('Visible Co')
            ->assertDontSee('Hidden Co');

        $response
            ->assertSee('Status: All')
            ->assertSee('Account: All')
            ->assertSee('Follow-up: All')
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending')
            ->assertSee('Rows: 15')
            ->assertSee('Lead')
            ->assertSee('Prospect')
            ->assertSee('Customer')
            ->assertSee('Churned');

        expect(Company::query()->ownedBy($user)->pluck('id')->all())
            ->toContain($visible->id)
            ->not->toContain($hidden->id);
    },
);

test(
    'index sanitizes search input and whitelists invalid filters',
    function () {
        $user = User::factory()->create();

        Company::factory()->count(2)->for($user)->create();

        $response = $this->actingAs($user)->get(
            route('companies.index', [
                'search' => '  <script>alert(1)</script>   Alpha   Beta  ',
                'status' => 'drop-table',
                'active' => 'sometimes',
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

        Company::factory()
            ->for($user)
            ->create(['name' => 'Orion Labs']);

        Company::factory()
            ->for($user)
            ->create(['name' => 'Northwind']);

        Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Orion Global']);

        $response = $this->actingAs($user)->get(
            route('companies.index', ['search' => 'Orion']),
        );

        $response
            ->assertOk()
            ->assertSee('Orion Labs')
            ->assertDontSee('Northwind')
            ->assertDontSee('Orion Global');

        $response->assertSee('value="Orion"', false);
    },
);

test(
    'index search can match primary contact name and email without cross-user leakage',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $matching = Company::factory()
            ->for($user)
            ->create(['name' => 'Zenith Labs']);

        $primaryContact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $matching->id,
                'name' => 'Priya SearchToken',
                'email' => 'priya.searchtoken@example.test',
            ]);

        $matching->update(['primary_contact_id' => $primaryContact->id]);

        Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $matching->id,
                'name' => 'Secondary SearchToken',
                'email' => 'secondary.searchtoken@example.test',
            ]);

        $hidden = Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Zenith Hidden']);

        $hiddenPrimaryContact = Contact::factory()
            ->for($otherUser)
            ->create([
                'company_id' => $hidden->id,
                'name' => 'Priya SearchToken',
                'email' => 'priya.searchtoken@example.test',
            ]);

        $hidden->update(['primary_contact_id' => $hiddenPrimaryContact->id]);

        $response = $this->actingAs($user)->get(
            route('companies.index', [
                'search' => 'Priya SearchToken example.test',
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('Zenith Labs')
            ->assertDontSee('Zenith Hidden');

        $secondaryResponse = $this->actingAs($user)->get(
            route('companies.index', ['search' => 'Secondary SearchToken']),
        );

        $secondaryResponse->assertOk()->assertDontSee('Zenith Labs');
    },
);

test(
    'index search requires all terms and ignores terms after the sixth',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $matching = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Systems',
                'industry' => 'Retail',
                'city' => 'Austin',
                'status' => 'lead',
                'source' => 'Inbound',
                'country' => 'Japan',
            ]);

        Company::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Partial',
                'industry' => 'Retail',
                'city' => 'Austin',
                'status' => 'lead',
                'source' => 'Inbound',
                'country' => 'Brazil',
            ]);

        Company::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Aurora Hidden',
                'industry' => 'Retail',
                'city' => 'Austin',
                'status' => 'lead',
                'source' => 'Inbound',
                'country' => 'Japan',
            ]);

        $search =
            'Aurora Retail Austin lead Inbound Japan seventh-term-ignored';

        $response = $this->actingAs($user)->get(
            route('companies.index', ['search' => $search]),
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
    'index paginates company records with default and custom per-page values',
    function () {
        $user = User::factory()->create();

        foreach (range(1, 32) as $index) {
            Company::factory()
                ->for($user)
                ->create([
                    'name' => "Pagination Co {$index}",
                    'updated_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                    'created_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                ]);
        }

        $defaultResponse = $this->actingAs($user)->get(
            route('companies.index'),
        );
        $customResponse = $this->actingAs($user)->get(
            route('companies.index', ['per_page' => 25]),
        );
        $invalidResponse = $this->actingAs($user)->get(
            route('companies.index', ['per_page' => 999]),
        );

        $defaultResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertSee('Pagination Co 32')
            ->assertSee('Pagination Co 18')
            ->assertDontSee('Pagination Co 17');

        $customResponse
            ->assertOk()
            ->assertSee('Rows: 25')
            ->assertSee('Pagination Co 17')
            ->assertDontSee('Pagination Co 7');

        $invalidResponse
            ->assertOk()
            ->assertSee('Rows: 15')
            ->assertDontSee('Pagination Co 17');
    },
);

test('index filters by status, activity, and follow-up date', function () {
    $user = User::factory()->create();

    $matching = Company::factory()
        ->for($user)
        ->create([
            'name' => 'Match Co',
            'status' => 'customer',
            'is_active' => false,
            'next_follow_up_at' => null,
        ]);

    Company::factory()
        ->for($user)
        ->create([
            'name' => 'Wrong Status',
            'status' => 'lead',
            'is_active' => false,
            'next_follow_up_at' => null,
        ]);

    Company::factory()
        ->for($user)
        ->create([
            'name' => 'Wrong Activity',
            'status' => 'customer',
            'is_active' => true,
            'next_follow_up_at' => null,
        ]);

    Company::factory()
        ->for($user)
        ->create([
            'name' => 'Has Follow Up',
            'status' => 'customer',
            'is_active' => false,
            'next_follow_up_at' => now()->addDay(),
        ]);

    $response = $this->actingAs($user)->get(
        route('companies.index', [
            'status' => 'customer',
            'active' => 'inactive',
            'follow_up' => 'none',
        ]),
    );

    $response
        ->assertOk()
        ->assertSee('Match Co')
        ->assertDontSee('Wrong Status')
        ->assertDontSee('Wrong Activity')
        ->assertDontSee('Has Follow Up');

    $response
        ->assertSee('Status: Customer')
        ->assertSee('Account: Inactive')
        ->assertSee('Follow-up: No date');

    expect($matching->fresh())
        ->not->toBeNull()
        ->and($matching->fresh()->name)
        ->toBe('Match Co');
});

test('index follow-up filter supports due and upcoming buckets', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-10 09:00:00'));

    try {
        $user = User::factory()->create();

        $duePast = Company::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDuePastCo',
                'next_follow_up_at' => '2026-02-09',
            ]);

        $dueToday = Company::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDueTodayCo',
                'next_follow_up_at' => '2026-02-10',
            ]);

        $upcoming = Company::factory()
            ->for($user)
            ->create([
                'name' => 'FollowLaterCo',
                'next_follow_up_at' => '2026-02-12',
            ]);

        Company::factory()
            ->for($user)
            ->create([
                'name' => 'FollowWithoutDateCo',
                'next_follow_up_at' => null,
            ]);

        $dueResponse = $this->actingAs($user)->get(
            route('companies.index', ['follow_up' => 'due']),
        );

        $upcomingResponse = $this->actingAs($user)->get(
            route('companies.index', ['follow_up' => 'upcoming']),
        );

        $dueResponse
            ->assertOk()
            ->assertSee('FollowDuePastCo')
            ->assertSee('FollowDueTodayCo')
            ->assertDontSee('FollowLaterCo')
            ->assertDontSee('FollowWithoutDateCo');

        $upcomingResponse
            ->assertOk()
            ->assertSee('FollowLaterCo')
            ->assertDontSee('FollowDuePastCo')
            ->assertDontSee('FollowDueTodayCo')
            ->assertDontSee('FollowWithoutDateCo');

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

        $beta = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Beta Co',
                'updated_at' => Carbon::parse('2026-01-01 10:00:00'),
            ]);

        $alpha = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Alpha Co',
                'updated_at' => Carbon::parse('2026-01-02 10:00:00'),
            ]);

        $sortedResponse = $this->actingAs($user)->get(
            route('companies.index', [
                'sort' => 'name',
                'direction' => 'asc',
            ]),
        );

        $fallbackResponse = $this->actingAs($user)->get(
            route('companies.index', [
                'sort' => 'not-allowed',
                'direction' => 'sideways',
            ]),
        );

        assertAppearsBefore(
            $sortedResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        assertAppearsBefore(
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

        $soon = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Soon',
                'next_follow_up_at' => '2026-02-11',
            ]);

        $later = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Later',
                'next_follow_up_at' => '2026-02-18',
            ]);

        $noDate = Company::factory()
            ->for($user)
            ->create([
                'name' => 'No Date',
                'next_follow_up_at' => null,
            ]);

        $ascResponse = $this->actingAs($user)->get(
            route('companies.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'asc',
            ]),
        );

        $descResponse = $this->actingAs($user)->get(
            route('companies.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'desc',
            ]),
        );

        assertAppearsBefore($ascResponse->getContent(), 'Soon', 'Later');
        assertAppearsBefore($ascResponse->getContent(), 'Later', 'No Date');

        assertAppearsBefore($descResponse->getContent(), 'Later', 'Soon');
        assertAppearsBefore($descResponse->getContent(), 'Soon', 'No Date');
    },
);

test('store sanitizes and normalizes incoming payload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(
        route('companies.store'),
        companyPayload([
            'name' => '  <b>Acme Sanitized</b>  ',
            'status' => 'LEAD',
            'email' => '  SALES@EXAMPLE.COM ',
            'billing_email' => ' BILLING@EXAMPLE.COM ',
            'website' => 'example.org',
            'linkedin_url' => 'linkedin.com/company/acme',
            'preferred_contact_method' => 'EMAIL',
            'notes' => "<script>alert(1)</script>  Important account\r\nSecond line  ",
        ]),
    );

    $company = Company::query()->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.show', $company))
        ->assertSessionHas('status', 'Company created successfully.');

    expect($company->user_id)
        ->toBe($user->id)
        ->and($company->name)
        ->toBe('Acme Sanitized')
        ->and($company->status)
        ->toBe('lead')
        ->and($company->email)
        ->toBe('sales@example.com')
        ->and($company->billing_email)
        ->toBe('billing@example.com')
        ->and($company->website)
        ->toBe('https://example.org')
        ->and($company->linkedin_url)
        ->toBe('https://linkedin.com/company/acme')
        ->and($company->preferred_contact_method)
        ->toBe('email')
        ->and($company->notes)
        ->toContain('Important account')
        ->and($company->notes)
        ->toContain('Second line')
        ->and($company->notes)
        ->not->toContain('<script>');
});

test(
    'companies routes use dedicated read/write throttle middleware',
    function () {
        $indexRoute = app('router')->getRoutes()->getByName('companies.index');
        $createRoute = app('router')
            ->getRoutes()
            ->getByName('companies.create');
        $showRoute = app('router')->getRoutes()->getByName('companies.show');
        $editRoute = app('router')->getRoutes()->getByName('companies.edit');
        $storeRoute = app('router')->getRoutes()->getByName('companies.store');
        $updateRoute = app('router')
            ->getRoutes()
            ->getByName('companies.update');
        $destroyRoute = app('router')
            ->getRoutes()
            ->getByName('companies.destroy');

        expect($indexRoute?->gatherMiddleware())
            ->toContain('throttle:companies-read')
            ->and($createRoute?->gatherMiddleware())
            ->toContain('throttle:companies-read')
            ->and($showRoute?->gatherMiddleware())
            ->toContain('throttle:companies-read')
            ->and($editRoute?->gatherMiddleware())
            ->toContain('throttle:companies-read')
            ->and($storeRoute?->gatherMiddleware())
            ->toContain('throttle:companies-write')
            ->and($updateRoute?->gatherMiddleware())
            ->toContain('throttle:companies-write')
            ->and($destroyRoute?->gatherMiddleware())
            ->toContain('throttle:companies-write')
            ->and($storeRoute?->gatherMiddleware())
            ->not->toContain('throttle:companies-read')
            ->and($indexRoute?->gatherMiddleware())
            ->not->toContain('throttle:companies-write');
    },
);

test(
    'authenticated users can render the create company page with metadata',
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('companies.create'));

        $response
            ->assertOk()
            ->assertSee('Create Company')
            ->assertSee('Company Profile')
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
    'users can create companies and data is always assigned to the authenticated user',
    function () {
        $user = User::factory()->create();

        $payload = companyPayload([
            'name' => 'Acme Corp',
            'status' => 'lead',
            'is_active' => '1',
        ]);

        $response = $this->actingAs($user)->post(
            route('companies.store'),
            $payload,
        );

        $company = Company::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.show', $company))
            ->assertSessionHas('status', 'Company created successfully.');

        expect($company->user_id)
            ->toBe($user->id)
            ->and($company->name)
            ->toBe('Acme Corp')
            ->and($company->status)
            ->toBe('lead')
            ->and($company->is_active)
            ->toBeTrue()
            ->and(Company::query()->count())
            ->toBe(1);
    },
);

test('company creation rejects user_id injection attempts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('companies.store'),
            companyPayload([
                'name' => 'Injection Co',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect(Company::query()->where('name', 'Injection Co')->exists())
        ->toBeFalse()
        ->and(Company::query()->count())
        ->toBe(0);
});

test('company creation rejects primary_contact_id before a company exists', function () {
    $user = User::factory()->create();

    $contact = Contact::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(
            route('companies.store'),
            companyPayload([
                'name' => 'Primary Contact Injection Co',
                'primary_contact_id' => $contact->id,
            ]),
        )
        ->assertSessionHasErrors(['primary_contact_id']);

    expect(
        Company::query()
            ->where('name', 'Primary Contact Injection Co')
            ->exists(),
    )->toBeFalse();
});

test('company creation validates required and constrained fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(
        route('companies.store'),
        companyPayload([
            'name' => '',
            'status' => 'invalid-status',
            'email' => 'invalid-email',
            'website' => 'not-a-url',
            'annual_revenue' => -1,
            'founded_year' => 1500,
            'employee_count' => 0,
            'phone' => 'abc',
            'next_follow_up_at' => '2026-01-01',
            'last_contacted_at' => '2026-02-01',
            'is_active' => 'maybe',
        ]),
    );

    $response->assertSessionHasErrors([
        'name',
        'status',
        'email',
        'website',
        'annual_revenue',
        'founded_year',
        'employee_count',
        'phone',
        'next_follow_up_at',
        'is_active',
    ]);

    expect(Company::query()->count())->toBe(0);
});

test('company creation rejects deceptive linkedin domains', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('companies.store'),
            companyPayload([
                'linkedin_url' => 'https://evil-linkedin.com/company/fake',
            ]),
        )
        ->assertSessionHasErrors(['linkedin_url']);

    expect(Company::query()->count())->toBe(0);
});

test(
    'company name must be unique per user but may be reused across users',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Company::factory()
            ->for($user)
            ->create(['name' => 'Shared Name']);

        $this->actingAs($user)
            ->post(
                route('companies.store'),
                companyPayload(['name' => 'Shared Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($otherUser)
            ->post(
                route('companies.store'),
                companyPayload(['name' => 'Shared Name']),
            )
            ->assertSessionHasNoErrors();

        expect(Company::query()->where('name', 'Shared Name')->count())
            ->toBe(2)
            ->and(
                Company::query()
                    ->where('user_id', $user->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1)
            ->and(
                Company::query()
                    ->where('user_id', $otherUser->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1);
    },
);

test('owners can view and edit their company records', function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create([
            'name' => 'Owner Co',
            'industry' => 'Finance',
            'status' => 'customer',
            'is_active' => false,
            'annual_revenue' => 3400000.12,
        ]);

    $showResponse = $this->actingAs($user)->get(
        route('companies.show', $company),
    );

    $showResponse
        ->assertOk()
        ->assertSee('Owner Co')
        ->assertSee('Finance')
        ->assertSee('Inactive')
        ->assertSee('Customer')
        ->assertSee('Company Information')
        ->assertSee('Contact Information');

    $editResponse = $this->actingAs($user)->get(
        route('companies.edit', $company),
    );

    $editResponse
        ->assertOk()
        ->assertSee('Edit Company')
        ->assertSee('Owner Co')
        ->assertSee('Save Changes')
        ->assertSee('Prospect')
        ->assertSee('Customer')
        ->assertSee('Linkedin')
        ->assertSee('Any');
});

test('non-owners cannot view or edit another users company', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $company = Company::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('companies.show', $company))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('companies.edit', $company))
        ->assertNotFound();
});

test('owners can update their companies with sanitized values', function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create([
            'name' => 'Before Update',
            'status' => 'lead',
            'email' => 'before@example.com',
            'is_active' => true,
            'notes' => 'Original note',
        ]);

    $payload = companyPayload([
        'name' => '  <b>After Update</b>  ',
        'status' => 'CUSTOMER',
        'email' => '  AFTER@EXAMPLE.COM ',
        'billing_email' => ' BILLING+UPDATED@EXAMPLE.COM ',
        'website' => 'updated.example.com',
        'linkedin_url' => 'linkedin.com/company/after-update',
        'preferred_contact_method' => 'PHONE',
        'notes' => "<script>bad()</script> Updated note\r\nLine two",
        'is_active' => '0',
    ]);

    $response = $this->actingAs($user)->put(
        route('companies.update', $company),
        $payload,
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.show', $company))
        ->assertSessionHas('status', 'Company updated successfully.');

    $company->refresh();

    expect($company->name)
        ->toBe('After Update')
        ->and($company->status)
        ->toBe('customer')
        ->and($company->email)
        ->toBe('after@example.com')
        ->and($company->billing_email)
        ->toBe('billing+updated@example.com')
        ->and($company->website)
        ->toBe('https://updated.example.com')
        ->and($company->linkedin_url)
        ->toBe('https://linkedin.com/company/after-update')
        ->and($company->preferred_contact_method)
        ->toBe('phone')
        ->and($company->notes)
        ->toContain('Updated note')
        ->and($company->notes)
        ->toContain('Line two')
        ->and($company->notes)
        ->not->toContain('<script>')
        ->and($company->is_active)
        ->toBeFalse()
        ->and($company->user_id)
        ->toBe($user->id);
});

test('owners can set a primary contact linked to the same company', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();

    $primaryContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $company->id,
        ]);

    $response = $this->actingAs($user)->put(
        route('companies.update', $company),
        companyPayload([
            'name' => $company->name,
            'primary_contact_id' => $primaryContact->id,
        ]),
    );

    $response->assertSessionHasNoErrors();

    expect($company->fresh()?->primary_contact_id)->toBe($primaryContact->id);
});

test(
    'company update rejects primary contacts that are not linked to the company or owner',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $otherCompany = Company::factory()->for($user)->create();

        $wrongCompanyContact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $otherCompany->id,
            ]);

        $foreignContact = Contact::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->put(
                route('companies.update', $company),
                companyPayload([
                    'name' => $company->name,
                    'primary_contact_id' => $wrongCompanyContact->id,
                ]),
            )
            ->assertSessionHasErrors(['primary_contact_id']);

        $this->actingAs($user)
            ->put(
                route('companies.update', $company),
                companyPayload([
                    'name' => $company->name,
                    'primary_contact_id' => $foreignContact->id,
                ]),
            )
            ->assertSessionHasErrors(['primary_contact_id']);

        expect($company->fresh()?->primary_contact_id)->toBeNull();
    },
);

test(
    'update validates uniqueness while allowing unchanged names and cross-user reuse',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $existing = Company::factory()
            ->for($user)
            ->create(['name' => 'Existing Name']);

        $target = Company::factory()
            ->for($user)
            ->create(['name' => 'Target Name']);

        Company::factory()
            ->for($otherUser)
            ->create(['name' => 'Shared Elsewhere']);

        $this->actingAs($user)
            ->put(
                route('companies.update', $target),
                companyPayload(['name' => 'Existing Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($user)
            ->put(
                route('companies.update', $target),
                companyPayload(['name' => 'Shared Elsewhere']),
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(
                route('companies.update', $target),
                companyPayload(['name' => 'Target Name']),
            )
            ->assertSessionHasNoErrors();

        expect($existing->refresh()->name)
            ->toBe('Existing Name')
            ->and($target->refresh()->name)
            ->toBe('Target Name');
    },
);

test('owners cannot change company ownership during update', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $company = Company::factory()
        ->for($owner)
        ->create(['name' => 'Ownership Locked']);

    $this->actingAs($owner)
        ->put(
            route('companies.update', $company),
            companyPayload([
                'name' => 'Ownership Locked',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect($company->fresh()->user_id)
        ->toBe($owner->id)
        ->and($company->fresh()->name)
        ->toBe('Ownership Locked');
});

test('non-owners cannot update another users company', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $company = Company::factory()
        ->for($owner)
        ->create(['name' => 'Protected Co']);

    $this->actingAs($intruder)
        ->put(
            route('companies.update', $company),
            companyPayload([
                'name' => 'Hacked Co',
            ]),
        )
        ->assertForbidden();

    expect($company->fresh()->name)->toBe('Protected Co');
});

test('owners can delete their companies', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('companies.destroy', $company))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas('status', 'Company deleted successfully.');

    $this->assertDatabaseMissing('companies', [
        'id' => $company->id,
    ]);

    expect(Company::query()->count())->toBe(0);
});

test('non-owners cannot delete another users company', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $company = Company::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('companies.destroy', $company))
        ->assertNotFound();

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'user_id' => $owner->id,
    ]);
});

test(
    'index shows an empty-state message when there are no companies',
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('companies.index'));

        $response
            ->assertOk()
            ->assertSee(
                'No companies found with the current search/filter settings.',
            )
            ->assertSee('Create your first company')
            ->assertSee('Rows: 15');
    },
);
