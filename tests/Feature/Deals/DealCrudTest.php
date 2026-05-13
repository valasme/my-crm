<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Carbon;

function dealPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->sentence(3),
            'type' => 'new_business',
            'status' => 'lead',
            'source' => 'Inbound',
            'amount' => '25000.00',
            'currency' => 'USD',
            'probability' => '25',
            'deal_at' => '2026-01-10',
            'expected_close_at' => '2026-02-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => '1',
            'outcome' => 'Discussed scope and agreed next steps.',
            'notes' => 'Follow-up deal for Q1 plan.',
        ],
        $overrides,
    );
}

function dealsTableContent(string $content): string
{
    $tableStart = strpos($content, 'id="deals-table-description"');

    if ($tableStart === false) {
        return $content;
    }

    return substr($content, $tableStart);
}

function assertDealAppearsBefore(
    string $content,
    string $first,
    string $second,
): void {
    $tableContent = dealsTableContent($content);

    $firstPosition = strpos($tableContent, $first);
    $secondPosition = strpos($tableContent, $second);

    expect($firstPosition)
        ->not->toBeFalse()
        ->and($secondPosition)
        ->not->toBeFalse();

    expect($firstPosition)->toBeLessThan($secondPosition);
}

test('guests are redirected from all deal routes', function () {
    $deal = Deal::factory()->create();

    $this->get(route('deals.index'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('deals.create'))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->post(route('deals.store'), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('deals.show', $deal))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->get(route('deals.edit', $deal))
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->put(route('deals.update', $deal), [])
        ->assertRedirect(route('login'))
        ->assertStatus(302);

    $this->delete(route('deals.destroy', $deal))
        ->assertRedirect(route('login'))
        ->assertStatus(302);
});

test(
    'index lists only authenticated users deals and returns default view metadata',
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

        $visible = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Visible Deal',
                'company_id' => $company->id,
                'contact_id' => $contact->id,
            ]);

        $hidden = Deal::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Hidden Deal',
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
            ]);

        $response = $this->actingAs($user)->get(route('deals.index'));

        $response
            ->assertOk()
            ->assertSee('Visible Deal')
            ->assertDontSee('Hidden Deal');

        $response
            ->assertSee('Stage: All')
            ->assertSee('Type: All')
            ->assertSee('State: All')
            ->assertSee('Company: All companies')
            ->assertSee('Contact: All contacts')
            ->assertSee('Follow-up: All')
            ->assertSee('Sort: Recently updated')
            ->assertSee('Order: Descending')
            ->assertSee('Rows: 15')
            ->assertSee('Lead')
            ->assertSee('Qualified')
            ->assertSee('Proposal')
            ->assertSee('Negotiation')
            ->assertSee('Won')
            ->assertSee('Lost')
            ->assertSee('New Business')
            ->assertSee('Expansion')
            ->assertSee('Renewal')
            ->assertSee('Services');

        expect(Deal::query()->ownedBy($user)->pluck('id')->all())
            ->toContain($visible->id)
            ->not->toContain($hidden->id);
    },
);

test(
    'index sanitizes search input and whitelists invalid filters',
    function () {
        $user = User::factory()->create();

        Deal::factory()->count(2)->for($user)->create();

        $response = $this->actingAs($user)->get(
            route('deals.index', [
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
            ->assertSee('Stage: All')
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

        Deal::factory()
            ->for($user)
            ->create(['name' => 'Orion Deal']);

        Deal::factory()
            ->for($user)
            ->create(['name' => 'Northwind Deal']);

        Deal::factory()
            ->for($otherUser)
            ->create(['name' => 'Orion Hidden']);

        $response = $this->actingAs($user)->get(
            route('deals.index', ['search' => 'Orion']),
        );

        $response
            ->assertOk()
            ->assertSee('Orion Deal')
            ->assertDontSee('Northwind Deal')
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

        $matching = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Systems',
                'type' => 'new_business',
                'status' => 'lead',
                'source' => 'Inbound',
                'outcome' => 'Japan confirmed',
                'company_id' => $usersCompany->id,
                'contact_id' => $usersContact->id,
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Aurora Partial',
                'type' => 'new_business',
                'status' => 'lead',
                'source' => 'Inbound',
                'outcome' => 'Brazil pending',
                'company_id' => $usersCompany->id,
                'contact_id' => $usersContact->id,
            ]);

        Deal::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Aurora Hidden',
                'type' => 'new_business',
                'status' => 'lead',
                'source' => 'Inbound',
                'outcome' => 'Japan confirmed',
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
            ]);

        $search =
            'Aurora new business lead inbound japan confirmed seventh-term-ignored';

        $response = $this->actingAs($user)->get(
            route('deals.index', ['search' => $search]),
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
    'index paginates deal records with default and custom per-page values',
    function () {
        $user = User::factory()->create();

        foreach (range(1, 32) as $index) {
            Deal::factory()
                ->for($user)
                ->create([
                    'name' => "Pagination Deal {$index}",
                    'updated_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                    'created_at' => Carbon::parse(
                        '2026-01-01 00:00:00',
                    )->addMinutes($index),
                ]);
        }

        $defaultResponse = $this->actingAs($user)->get(route('deals.index'));
        $customResponse = $this->actingAs($user)->get(
            route('deals.index', ['per_page' => 25]),
        );
        $invalidResponse = $this->actingAs($user)->get(
            route('deals.index', ['per_page' => 999]),
        );

        $defaultResponse->assertOk()->assertSee('Rows: 15');

        $customResponse->assertOk()->assertSee('Rows: 25');

        $invalidResponse->assertOk()->assertSee('Rows: 15');

        $defaultTable = dealsTableContent($defaultResponse->getContent());
        $customTable = dealsTableContent($customResponse->getContent());
        $invalidTable = dealsTableContent($invalidResponse->getContent());

        expect($defaultTable)
            ->toContain('Pagination Deal 32')
            ->toContain('Pagination Deal 18')
            ->not->toContain('Pagination Deal 17');

        expect($customTable)
            ->toContain('Pagination Deal 17')
            ->not->toContain('Pagination Deal 7');

        expect($invalidTable)->not->toContain('Pagination Deal 17');
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

        $matching = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Match Deal',
                'status' => 'won',
                'type' => 'expansion',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
                'closed_at' => '2026-01-15',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Status',
                'status' => 'lead',
                'type' => 'expansion',
                'is_active' => true,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Type',
                'status' => 'won',
                'type' => 'new_business',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
                'closed_at' => '2026-01-16',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong State',
                'status' => 'lead',
                'type' => 'expansion',
                'is_active' => true,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => null,
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Company',
                'status' => 'won',
                'type' => 'expansion',
                'is_active' => false,
                'company_id' => $otherCompany->id,
                'contact_id' => $otherContact->id,
                'next_follow_up_at' => null,
                'closed_at' => '2026-01-17',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Wrong Contact',
                'status' => 'won',
                'type' => 'expansion',
                'is_active' => false,
                'company_id' => $matchingCompany->id,
                'contact_id' => $otherContact->id,
                'next_follow_up_at' => null,
                'closed_at' => '2026-01-18',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Has Follow Up',
                'status' => 'lead',
                'type' => 'expansion',
                'is_active' => true,
                'company_id' => $matchingCompany->id,
                'contact_id' => $matchingContact->id,
                'next_follow_up_at' => now()->addDay(),
            ]);

        $response = $this->actingAs($user)->get(
            route('deals.index', [
                'status' => 'won',
                'type' => 'expansion',
                'active' => 'inactive',
                'company' => (string) $matchingCompany->id,
                'contact' => (string) $matchingContact->id,
                'follow_up' => 'none',
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('Match Deal')
            ->assertDontSee('Wrong Status')
            ->assertDontSee('Wrong Type')
            ->assertDontSee('Wrong State')
            ->assertDontSee('Wrong Company')
            ->assertDontSee('Wrong Contact')
            ->assertDontSee('Has Follow Up');

        $response
            ->assertSee('Stage: Won')
            ->assertSee('Type: Expansion')
            ->assertSee('State: Inactive')
            ->assertSee('Company: Match Company')
            ->assertSee('Contact: Match Contact')
            ->assertSee('Follow-up: No date');

        expect($matching->fresh())
            ->not->toBeNull()
            ->and($matching->fresh()->name)
            ->toBe('Match Deal');
    },
);

test('index follow-up filter supports due and upcoming buckets', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-10 09:00:00'));

    try {
        $user = User::factory()->create();

        $duePast = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDuePastDeal',
                'next_follow_up_at' => '2026-02-09',
            ]);

        $dueToday = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'FollowDueTodayDeal',
                'next_follow_up_at' => '2026-02-10',
            ]);

        $upcoming = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'FollowLaterDeal',
                'next_follow_up_at' => '2026-02-12',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'FollowWithoutDateDeal',
                'next_follow_up_at' => null,
            ]);

        $dueResponse = $this->actingAs($user)->get(
            route('deals.index', ['follow_up' => 'due']),
        );

        $upcomingResponse = $this->actingAs($user)->get(
            route('deals.index', ['follow_up' => 'upcoming']),
        );

        $dueResponse
            ->assertOk()
            ->assertSee('FollowDuePastDeal')
            ->assertSee('FollowDueTodayDeal')
            ->assertDontSee('FollowLaterDeal')
            ->assertDontSee('FollowWithoutDateDeal');

        $upcomingResponse
            ->assertOk()
            ->assertSee('FollowLaterDeal')
            ->assertDontSee('FollowDuePastDeal')
            ->assertDontSee('FollowDueTodayDeal')
            ->assertDontSee('FollowWithoutDateDeal');

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

        $beta = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Beta Deal',
                'updated_at' => Carbon::parse('2026-01-01 10:00:00'),
            ]);

        $alpha = Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Alpha Deal',
                'updated_at' => Carbon::parse('2026-01-02 10:00:00'),
            ]);

        $sortedResponse = $this->actingAs($user)->get(
            route('deals.index', [
                'sort' => 'name',
                'direction' => 'asc',
            ]),
        );

        $fallbackResponse = $this->actingAs($user)->get(
            route('deals.index', [
                'sort' => 'not-allowed',
                'direction' => 'sideways',
            ]),
        );

        assertDealAppearsBefore(
            $sortedResponse->getContent(),
            $alpha->name,
            $beta->name,
        );

        assertDealAppearsBefore(
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

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Soon',
                'next_follow_up_at' => '2026-02-11',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'Later',
                'next_follow_up_at' => '2026-02-18',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'name' => 'No Date',
                'next_follow_up_at' => null,
            ]);

        $ascResponse = $this->actingAs($user)->get(
            route('deals.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'asc',
            ]),
        );

        $descResponse = $this->actingAs($user)->get(
            route('deals.index', [
                'sort' => 'next_follow_up_at',
                'direction' => 'desc',
            ]),
        );

        assertDealAppearsBefore($ascResponse->getContent(), 'Soon', 'Later');
        assertDealAppearsBefore($ascResponse->getContent(), 'Later', 'No Date');

        assertDealAppearsBefore($descResponse->getContent(), 'Later', 'Soon');
        assertDealAppearsBefore($descResponse->getContent(), 'Soon', 'No Date');
    },
);

test('store sanitizes and normalizes incoming payload', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();
    $contact = Contact::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    $response = $this->actingAs($user)->post(
        route('deals.store'),
        dealPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => '  <b>Quarterly Review</b>  ',
            'type' => 'EXPANSION',
            'status' => 'WON',
            'source' => '  <i>Inbound</i>  ',
            'outcome' => '  <script>alert(1)</script> Deal closed  ',
            'notes' => "<script>bad()</script>  Important deal\r\nSecond line  ",
            'is_active' => '0',
            'next_follow_up_at' => null,
        ]),
    );

    $deal = Deal::query()->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('deals.show', $deal))
        ->assertSessionHas('status', 'Deal created successfully.');

    expect($deal->user_id)
        ->toBe($user->id)
        ->and($deal->company_id)
        ->toBe($company->id)
        ->and($deal->contact_id)
        ->toBe($contact->id)
        ->and($deal->name)
        ->toBe('Quarterly Review')
        ->and($deal->type)
        ->toBe('expansion')
        ->and($deal->status)
        ->toBe('won')
        ->and($deal->source)
        ->toBe('Inbound')
        ->and($deal->outcome)
        ->toBe('alert(1) Deal closed')
        ->and($deal->notes)
        ->toContain('Important deal')
        ->and($deal->notes)
        ->toContain('Second line')
        ->and($deal->notes)
        ->not->toContain('<script>')
        ->and($deal->is_active)
        ->toBeFalse()
        ->and($deal->probability)
        ->toBe(100)
        ->and($deal->next_follow_up_at)
        ->toBeNull()
        ->and($deal->closed_at)
        ->not->toBeNull();
});

test('deals routes use dedicated read/write throttle middleware', function () {
    $indexRoute = app('router')->getRoutes()->getByName('deals.index');
    $createRoute = app('router')->getRoutes()->getByName('deals.create');
    $showRoute = app('router')->getRoutes()->getByName('deals.show');
    $editRoute = app('router')->getRoutes()->getByName('deals.edit');
    $storeRoute = app('router')->getRoutes()->getByName('deals.store');
    $updateRoute = app('router')->getRoutes()->getByName('deals.update');
    $destroyRoute = app('router')->getRoutes()->getByName('deals.destroy');

    expect($indexRoute?->gatherMiddleware())
        ->toContain('throttle:deals-read')
        ->and($createRoute?->gatherMiddleware())
        ->toContain('throttle:deals-read')
        ->and($showRoute?->gatherMiddleware())
        ->toContain('throttle:deals-read')
        ->and($editRoute?->gatherMiddleware())
        ->toContain('throttle:deals-read')
        ->and($storeRoute?->gatherMiddleware())
        ->toContain('throttle:deals-write')
        ->and($updateRoute?->gatherMiddleware())
        ->toContain('throttle:deals-write')
        ->and($destroyRoute?->gatherMiddleware())
        ->toContain('throttle:deals-write')
        ->and($storeRoute?->gatherMiddleware())
        ->not->toContain('throttle:deals-read')
        ->and($indexRoute?->gatherMiddleware())
        ->not->toContain('throttle:deals-write');
});

test(
    'authenticated users can render the create deal page with metadata',
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

        $response = $this->actingAs($user)->get(route('deals.create'));

        $response
            ->assertOk()
            ->assertSee('Create Deal')
            ->assertSee('Deal Context')
            ->assertSee('Revenue &amp; Timeline', false)
            ->assertSee('Company (optional)')
            ->assertSee('Contact (optional)')
            ->assertSee('Acme Company')
            ->assertSee('Acme Contact')
            ->assertSee('Lead')
            ->assertSee('Qualified')
            ->assertSee('Proposal')
            ->assertSee('Negotiation')
            ->assertSee('Won')
            ->assertSee('Lost')
            ->assertSee('New Business')
            ->assertSee('Expansion')
            ->assertSee('Renewal')
            ->assertSee('Services');
    },
);

test(
    'users can create deals and data is always assigned to the authenticated user',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();
        $contact = Contact::factory()
            ->for($user)
            ->create(['company_id' => $company->id]);

        $payload = dealPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Intro Call',
            'type' => 'new_business',
            'status' => 'lead',
            'is_active' => '1',
        ]);

        $response = $this->actingAs($user)->post(
            route('deals.store'),
            $payload,
        );

        $deal = Deal::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('deals.show', $deal))
            ->assertSessionHas('status', 'Deal created successfully.');

        expect($deal->user_id)
            ->toBe($user->id)
            ->and($deal->company_id)
            ->toBe($company->id)
            ->and($deal->contact_id)
            ->toBe($contact->id)
            ->and($deal->name)
            ->toBe('Intro Call')
            ->and($deal->type)
            ->toBe('new_business')
            ->and($deal->status)
            ->toBe('lead')
            ->and($deal->is_active)
            ->toBeTrue()
            ->and(Deal::query()->count())
            ->toBe(1);
    },
);

test('deal creation rejects user_id injection attempts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('deals.store'),
            dealPayload([
                'name' => 'Injection Deal',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect(Deal::query()->where('name', 'Injection Deal')->exists())
        ->toBeFalse()
        ->and(Deal::query()->count())
        ->toBe(0);
});

test('deal creation validates required and constrained fields', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUsersCompany = Company::factory()->for($otherUser)->create();
    $otherUsersContact = Contact::factory()
        ->for($otherUser)
        ->create(['company_id' => $otherUsersCompany->id]);

    $response = $this->actingAs($user)->post(
        route('deals.store'),
        dealPayload([
            'name' => '',
            'type' => 'invalid-type',
            'status' => 'invalid-status',
            'deal_at' => '',
            'company_id' => $otherUsersCompany->id,
            'contact_id' => $otherUsersContact->id,
            'is_active' => 'maybe',
        ]),
    );

    $response->assertSessionHasErrors([
        'name',
        'type',
        'status',
        'deal_at',
        'company_id',
        'contact_id',
        'is_active',
    ]);

    expect(Deal::query()->count())->toBe(0);
});

test(
    'deal name must be unique per user but may be reused across users',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Deal::factory()
            ->for($user)
            ->create(['name' => 'Shared Name']);

        $this->actingAs($user)
            ->post(route('deals.store'), dealPayload(['name' => 'Shared Name']))
            ->assertSessionHasErrors(['name']);

        $this->actingAs($otherUser)
            ->post(route('deals.store'), dealPayload(['name' => 'Shared Name']))
            ->assertSessionHasNoErrors();

        expect(Deal::query()->where('name', 'Shared Name')->count())
            ->toBe(2)
            ->and(
                Deal::query()
                    ->where('user_id', $user->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1)
            ->and(
                Deal::query()
                    ->where('user_id', $otherUser->id)
                    ->where('name', 'Shared Name')
                    ->count(),
            )
            ->toBe(1);
    },
);

test('owners can view and edit their deal records', function () {
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

    $deal = Deal::factory()
        ->for($user)
        ->create([
            'name' => 'Owner Deal',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'type' => 'renewal',
            'status' => 'won',
            'is_active' => false,
            'closed_at' => '2026-01-12',
        ]);

    $showResponse = $this->actingAs($user)->get(route('deals.show', $deal));

    $showResponse
        ->assertOk()
        ->assertSee('Owner Deal')
        ->assertSee('Owner Company')
        ->assertSee('Owner Contact')
        ->assertSee('Inactive')
        ->assertSee('Won')
        ->assertSee('Deal Details')
        ->assertSee('Record Context');

    $editResponse = $this->actingAs($user)->get(route('deals.edit', $deal));

    $editResponse
        ->assertOk()
        ->assertSee('Edit Deal')
        ->assertSee('Owner Deal')
        ->assertSee('Save Changes')
        ->assertSee('Lead')
        ->assertSee('Won')
        ->assertSee('New Business')
        ->assertSee('Renewal');
});

test('non-owners cannot view or edit another users deal', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $deal = Deal::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('deals.show', $deal))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route('deals.edit', $deal))
        ->assertNotFound();
});

test('owners can update their deals with sanitized values', function () {
    $user = User::factory()->create();

    $companyA = Company::factory()->for($user)->create();
    $companyB = Company::factory()->for($user)->create();

    $contactA = Contact::factory()
        ->for($user)
        ->create(['company_id' => $companyA->id]);

    $contactB = Contact::factory()
        ->for($user)
        ->create(['company_id' => $companyB->id]);

    $deal = Deal::factory()
        ->for($user)
        ->create([
            'company_id' => $companyA->id,
            'contact_id' => $contactA->id,
            'name' => 'Before Update',
            'type' => 'new_business',
            'status' => 'lead',
            'source' => 'Outbound',
            'is_active' => true,
            'outcome' => 'Pending follow-up',
            'notes' => 'Original note',
        ]);

    $payload = dealPayload([
        'company_id' => $companyB->id,
        'contact_id' => $contactB->id,
        'name' => '  <b>After Update</b>  ',
        'type' => 'EXPANSION',
        'status' => 'WON',
        'source' => '  <i>Inbound</i>  ',
        'outcome' => '  <script>alert(1)</script> Completed successfully  ',
        'notes' => "<script>bad()</script> Updated note\r\nLine two",
        'is_active' => '0',
        'next_follow_up_at' => null,
    ]);

    $response = $this->actingAs($user)->put(
        route('deals.update', $deal),
        $payload,
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('deals.show', $deal))
        ->assertSessionHas('status', 'Deal updated successfully.');

    $deal->refresh();

    expect($deal->company_id)
        ->toBe($companyB->id)
        ->and($deal->contact_id)
        ->toBe($contactB->id)
        ->and($deal->name)
        ->toBe('After Update')
        ->and($deal->type)
        ->toBe('expansion')
        ->and($deal->status)
        ->toBe('won')
        ->and($deal->source)
        ->toBe('Inbound')
        ->and($deal->outcome)
        ->toBe('alert(1) Completed successfully')
        ->and($deal->notes)
        ->toContain('Updated note')
        ->and($deal->notes)
        ->toContain('Line two')
        ->and($deal->notes)
        ->not->toContain('<script>')
        ->and($deal->is_active)
        ->toBeFalse()
        ->and($deal->probability)
        ->toBe(100)
        ->and($deal->next_follow_up_at)
        ->toBeNull()
        ->and($deal->closed_at)
        ->not->toBeNull()
        ->and($deal->user_id)
        ->toBe($user->id);
});

test('updating a deal stage compacts the previous stage ordering', function () {
    $user = User::factory()->create();

    $firstLead = Deal::factory()
        ->for($user)
        ->create([
            'status' => 'lead',
            'sort_order' => 0,
        ]);

    $middleLead = Deal::factory()
        ->for($user)
        ->create([
            'status' => 'lead',
            'sort_order' => 1,
        ]);

    $lastLead = Deal::factory()
        ->for($user)
        ->create([
            'status' => 'lead',
            'sort_order' => 2,
        ]);

    $this->actingAs($user)
        ->put(
            route('deals.update', $middleLead),
            dealPayload([
                'name' => $middleLead->name,
                'status' => 'won',
                'type' => 'expansion',
                'is_active' => '0',
                'next_follow_up_at' => null,
                'closed_at' => '2026-02-15',
            ]),
        )
        ->assertSessionHasNoErrors();

    expect($firstLead->fresh()->sort_order)
        ->toBe(0)
        ->and($lastLead->fresh()->sort_order)
        ->toBe(1)
        ->and($middleLead->fresh()->status)
        ->toBe('won')
        ->and($middleLead->fresh()->sort_order)
        ->toBe(0);
});

test(
    'update validates uniqueness while allowing unchanged names and cross-user reuse',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $existing = Deal::factory()
            ->for($user)
            ->create(['name' => 'Existing Name']);

        $target = Deal::factory()
            ->for($user)
            ->create(['name' => 'Target Name']);

        Deal::factory()
            ->for($otherUser)
            ->create(['name' => 'Shared Elsewhere']);

        $this->actingAs($user)
            ->put(
                route('deals.update', $target),
                dealPayload(['name' => 'Existing Name']),
            )
            ->assertSessionHasErrors(['name']);

        $this->actingAs($user)
            ->put(
                route('deals.update', $target),
                dealPayload(['name' => 'Shared Elsewhere']),
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(
                route('deals.update', $target),
                dealPayload(['name' => 'Target Name']),
            )
            ->assertSessionHasNoErrors();

        expect($existing->refresh()->name)
            ->toBe('Existing Name')
            ->and($target->refresh()->name)
            ->toBe('Target Name');
    },
);

test('owners cannot change deal ownership during update', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $deal = Deal::factory()
        ->for($owner)
        ->create(['name' => 'Ownership Locked']);

    $this->actingAs($owner)
        ->put(
            route('deals.update', $deal),
            dealPayload([
                'name' => 'Ownership Locked',
                'user_id' => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(['user_id']);

    expect($deal->fresh()->user_id)
        ->toBe($owner->id)
        ->and($deal->fresh()->name)
        ->toBe('Ownership Locked');
});

test('non-owners cannot update another users deal', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $deal = Deal::factory()
        ->for($owner)
        ->create(['name' => 'Protected Deal']);

    $this->actingAs($intruder)
        ->put(
            route('deals.update', $deal),
            dealPayload([
                'name' => 'Hacked Deal',
            ]),
        )
        ->assertNotFound();

    expect($deal->fresh()->name)->toBe('Protected Deal');
});

test('owners can delete their deals', function () {
    $user = User::factory()->create();

    $deal = Deal::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('deals.destroy', $deal))
        ->assertRedirect(route('deals.index'))
        ->assertSessionHas('status', 'Deal deleted successfully.');

    $this->assertDatabaseMissing('deals', [
        'id' => $deal->id,
    ]);

    expect(Deal::query()->count())->toBe(0);
});

test('non-owners cannot delete another users deal', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $deal = Deal::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route('deals.destroy', $deal))
        ->assertNotFound();

    $this->assertDatabaseHas('deals', [
        'id' => $deal->id,
        'user_id' => $owner->id,
    ]);
});

test('deleting a deal compacts stage sort ordering', function () {
    $user = User::factory()->create();

    $firstLead = Deal::factory()
        ->for($user)
        ->create([
            'status' => 'lead',
            'sort_order' => 0,
        ]);

    $middleLead = Deal::factory()
        ->for($user)
        ->create([
            'status' => 'lead',
            'sort_order' => 1,
        ]);

    $lastLead = Deal::factory()
        ->for($user)
        ->create([
            'status' => 'lead',
            'sort_order' => 2,
        ]);

    $this->actingAs($user)
        ->delete(route('deals.destroy', $middleLead))
        ->assertRedirect(route('deals.index'));

    expect($firstLead->fresh()->sort_order)
        ->toBe(0)
        ->and($lastLead->fresh()->sort_order)
        ->toBe(1);
});

test('index shows an empty-state message when there are no deals', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('deals.index'));

    $response
        ->assertOk()
        ->assertSee('No deals found with the current search/filter settings.')
        ->assertSee('Create your first deal')
        ->assertSee('Rows: 15');
});

test('won or lost deals must be inactive', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('deals.store'),
            dealPayload([
                'status' => 'won',
                'is_active' => '1',
                'next_follow_up_at' => null,
            ]),
        )
        ->assertSessionHasNoErrors();

    expect(Deal::query()->count())
        ->toBe(1)
        ->and(Deal::query()->value('is_active'))
        ->toBeFalse();
});

test('won or lost deals cannot keep a follow-up date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route('deals.store'),
            dealPayload([
                'status' => 'lost',
                'is_active' => '0',
                'next_follow_up_at' => '2026-02-05',
            ]),
        )
        ->assertSessionHasNoErrors();

    $deal = Deal::query()->first();

    expect($deal)
        ->not->toBeNull()
        ->and($deal->next_follow_up_at)
        ->toBeNull()
        ->and($deal->probability)
        ->toBe(0);
});

test(
    'deals stay linked to activities through model relationships',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
            ]);

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
            ]);

        expect($deal->fresh()?->activity)
            ->not->toBeNull()
            ->and($deal->fresh()?->activity?->is($activity))
            ->toBeTrue()
            ->and($activity->deals()->whereKey($deal->id)->exists())
            ->toBeTrue();
    },
);

test(
    'deal relationships must stay consistent across company contact and activity',
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
                route('deals.store'),
                dealPayload([
                    'company_id' => $companyA->id,
                    'contact_id' => $contactB->id,
                    'activity_id' => $activityB->id,
                    'next_follow_up_at' => null,
                ]),
            )
            ->assertSessionHasErrors(['contact_id', 'activity_id']);

        $response = $this->actingAs($user)->post(
            route('deals.store'),
            dealPayload([
                'company_id' => $companyA->id,
                'contact_id' => $contactA->id,
                'activity_id' => null,
                'next_follow_up_at' => '2026-02-05',
            ]),
        );

        $deal = Deal::query()->latest('id')->first();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('deals.show', $deal));

        expect($deal)
            ->not->toBeNull()
            ->and($deal->company_id)
            ->toBe($companyA->id)
            ->and($deal->contact_id)
            ->toBe($contactA->id);
    },
);
