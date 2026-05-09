<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;

function companyPayload(array $overrides = []): array
{
    return array_merge(
        [
            "name" => fake()->unique()->company(),
            "legal_name" => fake()->company() . " LLC",
            "status" => "lead",
            "industry" => "Technology",
            "source" => "Inbound",
            "ownership_type" => "Private",
            "founded_year" => 2010,
            "employee_count" => 120,
            "annual_revenue" => 2500000.25,
            "website" => "https://example.com",
            "linkedin_url" => "https://linkedin.com/company/example",
            "email" => "hello@example.com",
            "billing_email" => "billing@example.com",
            "phone" => "+1-555-0100",
            "support_phone" => "+1-555-0111",
            "timezone" => "UTC",
            "preferred_contact_method" => "email",
            "tax_id" => "12-3456789",
            "primary_contact_name" => "Alex Jordan",
            "primary_contact_email" => "alex@example.com",
            "primary_contact_phone" => "+1-555-0199",
            "address_line_1" => "123 Main St",
            "address_line_2" => "Suite 400",
            "city" => "Austin",
            "state" => "TX",
            "postal_code" => "78701",
            "country" => "United States",
            "last_contacted_at" => "2026-01-10",
            "next_follow_up_at" => "2026-01-20",
            "is_active" => "1",
            "notes" => "Enterprise account with multi-year opportunity.",
        ],
        $overrides,
    );
}

test("guests are redirected from all company routes", function () {
    $company = Company::factory()->create();

    $this->get(route("companies.index"))
        ->assertRedirect(route("login"))
        ->assertStatus(302);

    $this->get(route("companies.create"))
        ->assertRedirect(route("login"))
        ->assertStatus(302);

    $this->post(route("companies.store"), [])
        ->assertRedirect(route("login"))
        ->assertStatus(302);

    $this->get(route("companies.show", $company))
        ->assertRedirect(route("login"))
        ->assertStatus(302);

    $this->get(route("companies.edit", $company))
        ->assertRedirect(route("login"))
        ->assertStatus(302);

    $this->put(route("companies.update", $company), [])
        ->assertRedirect(route("login"))
        ->assertStatus(302);

    $this->delete(route("companies.destroy", $company))
        ->assertRedirect(route("login"))
        ->assertStatus(302);
});

test(
    "index lists only authenticated users companies and returns default view metadata",
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $visible = Company::factory()
            ->for($user)
            ->create(["name" => "Visible Co"]);

        $hidden = Company::factory()
            ->for($otherUser)
            ->create(["name" => "Hidden Co"]);

        $response = $this->actingAs($user)->get(route("companies.index"));

        $response
            ->assertOk()
            ->assertSee("Visible Co")
            ->assertDontSee("Hidden Co");

        $companies = $response->viewData("companies");
        $filters = $response->viewData("filters");

        expect($companies->pluck("id")->all())
            ->toContain($visible->id)
            ->not->toContain($hidden->id)
            ->and($companies->total())
            ->toBe(1)
            ->and($response->viewData("search"))
            ->toBe("")
            ->and($filters["search"])
            ->toBe("")
            ->and($filters["status"])
            ->toBe("all")
            ->and($filters["active"])
            ->toBe("all")
            ->and($filters["follow_up"])
            ->toBe("all")
            ->and($filters["sort"])
            ->toBe("updated_at")
            ->and($filters["direction"])
            ->toBe("desc")
            ->and($filters["per_page"])
            ->toBe(15)
            ->and($response->viewData("statuses"))
            ->toBe(Company::statuses())
            ->and($response->viewData("perPageOptions"))
            ->toBe([15, 25, 50]);

        $sortOptions = $response->viewData("sortOptions");

        expect(array_keys($sortOptions))->toBe([
            "updated_at",
            "name",
            "status",
            "next_follow_up_at",
            "created_at",
        ]);
    },
);

test(
    "index sanitizes search input and whitelists invalid filters",
    function () {
        $user = User::factory()->create();

        Company::factory()->count(2)->for($user)->create();

        $response = $this->actingAs($user)->get(
            route("companies.index", [
                "search" => "  <script>alert(1)</script>   Alpha   Beta  ",
                "status" => "drop-table",
                "active" => "sometimes",
                "follow_up" => "later",
                "sort" => "not-allowed",
                "direction" => "sideways",
                "per_page" => 999,
            ]),
        );

        $response->assertOk();

        $filters = $response->viewData("filters");

        expect($response->viewData("search"))
            ->toBe("alert(1) Alpha Beta")
            ->and($filters["search"])
            ->toBe("alert(1) Alpha Beta")
            ->and($filters["status"])
            ->toBe("all")
            ->and($filters["active"])
            ->toBe("all")
            ->and($filters["follow_up"])
            ->toBe("all")
            ->and($filters["sort"])
            ->toBe("updated_at")
            ->and($filters["direction"])
            ->toBe("desc")
            ->and($filters["per_page"])
            ->toBe(15)
            ->and($response->viewData("companies")->perPage())
            ->toBe(15);
    },
);

test(
    "index supports searching only within the authenticated users data",
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Company::factory()
            ->for($user)
            ->create(["name" => "Orion Labs"]);

        Company::factory()
            ->for($user)
            ->create(["name" => "Northwind"]);

        Company::factory()
            ->for($otherUser)
            ->create(["name" => "Orion Global"]);

        $response = $this->actingAs($user)->get(
            route("companies.index", ["search" => "Orion"]),
        );

        $response
            ->assertOk()
            ->assertSee("Orion Labs")
            ->assertDontSee("Northwind")
            ->assertDontSee("Orion Global");

        expect($response->viewData("search"))
            ->toBe("Orion")
            ->and($response->viewData("companies")->total())
            ->toBe(1);
    },
);

test(
    "index search requires all terms and ignores terms after the sixth",
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $matching = Company::factory()
            ->for($user)
            ->create([
                "name" => "Aurora Systems",
                "industry" => "Retail",
                "city" => "Austin",
                "status" => "lead",
                "source" => "Inbound",
                "country" => "Japan",
            ]);

        Company::factory()
            ->for($user)
            ->create([
                "name" => "Aurora Partial",
                "industry" => "Retail",
                "city" => "Austin",
                "status" => "lead",
                "source" => "Inbound",
                "country" => "Brazil",
            ]);

        Company::factory()
            ->for($otherUser)
            ->create([
                "name" => "Aurora Hidden",
                "industry" => "Retail",
                "city" => "Austin",
                "status" => "lead",
                "source" => "Inbound",
                "country" => "Japan",
            ]);

        $search =
            "Aurora Retail Austin lead Inbound Japan seventh-term-ignored";

        $response = $this->actingAs($user)->get(
            route("companies.index", ["search" => $search]),
        );

        $response
            ->assertOk()
            ->assertSee("Aurora Systems")
            ->assertDontSee("Aurora Partial")
            ->assertDontSee("Aurora Hidden");

        expect($response->viewData("companies")->pluck("id")->all())->toContain(
            $matching->id,
        );
    },
);

test(
    "index paginates company records with default and custom per-page values",
    function () {
        $user = User::factory()->create();

        Company::factory()->count(32)->for($user)->create();

        $defaultResponse = $this->actingAs($user)->get(
            route("companies.index"),
        );
        $customResponse = $this->actingAs($user)->get(
            route("companies.index", ["per_page" => 25]),
        );
        $invalidResponse = $this->actingAs($user)->get(
            route("companies.index", ["per_page" => 999]),
        );

        $defaultCompanies = $defaultResponse->viewData("companies");
        $customCompanies = $customResponse->viewData("companies");
        $invalidCompanies = $invalidResponse->viewData("companies");

        expect($defaultCompanies->total())
            ->toBe(32)
            ->and($defaultCompanies->count())
            ->toBe(15)
            ->and($defaultCompanies->perPage())
            ->toBe(15)
            ->and($customCompanies->total())
            ->toBe(32)
            ->and($customCompanies->count())
            ->toBe(25)
            ->and($customCompanies->perPage())
            ->toBe(25)
            ->and($customResponse->viewData("filters")["per_page"])
            ->toBe(25)
            ->and($invalidCompanies->perPage())
            ->toBe(15)
            ->and($invalidResponse->viewData("filters")["per_page"])
            ->toBe(15);
    },
);

test("index filters by status, activity, and follow-up date", function () {
    $user = User::factory()->create();

    $matching = Company::factory()
        ->for($user)
        ->create([
            "name" => "Match Co",
            "status" => "customer",
            "is_active" => false,
            "next_follow_up_at" => null,
        ]);

    Company::factory()
        ->for($user)
        ->create([
            "name" => "Wrong Status",
            "status" => "lead",
            "is_active" => false,
            "next_follow_up_at" => null,
        ]);

    Company::factory()
        ->for($user)
        ->create([
            "name" => "Wrong Activity",
            "status" => "customer",
            "is_active" => true,
            "next_follow_up_at" => null,
        ]);

    Company::factory()
        ->for($user)
        ->create([
            "name" => "Has Follow Up",
            "status" => "customer",
            "is_active" => false,
            "next_follow_up_at" => now()->addDay(),
        ]);

    $response = $this->actingAs($user)->get(
        route("companies.index", [
            "status" => "customer",
            "active" => "inactive",
            "follow_up" => "none",
        ]),
    );

    $response
        ->assertOk()
        ->assertSee("Match Co")
        ->assertDontSee("Wrong Status")
        ->assertDontSee("Wrong Activity")
        ->assertDontSee("Has Follow Up");

    $companyIds = $response->viewData("companies")->pluck("id");
    $filters = $response->viewData("filters");

    expect($companyIds)
        ->toContain($matching->id)
        ->and($filters["status"])
        ->toBe("customer")
        ->and($filters["active"])
        ->toBe("inactive")
        ->and($filters["follow_up"])
        ->toBe("none");
});

test("index follow-up filter supports due and upcoming buckets", function () {
    Carbon::setTestNow(Carbon::parse("2026-02-10 09:00:00"));

    try {
        $user = User::factory()->create();

        $duePast = Company::factory()
            ->for($user)
            ->create([
                "name" => "FollowDuePastCo",
                "next_follow_up_at" => "2026-02-09",
            ]);

        $dueToday = Company::factory()
            ->for($user)
            ->create([
                "name" => "FollowDueTodayCo",
                "next_follow_up_at" => "2026-02-10",
            ]);

        $upcoming = Company::factory()
            ->for($user)
            ->create([
                "name" => "FollowLaterCo",
                "next_follow_up_at" => "2026-02-12",
            ]);

        Company::factory()
            ->for($user)
            ->create([
                "name" => "FollowWithoutDateCo",
                "next_follow_up_at" => null,
            ]);

        $dueResponse = $this->actingAs($user)->get(
            route("companies.index", ["follow_up" => "due"]),
        );

        $upcomingResponse = $this->actingAs($user)->get(
            route("companies.index", ["follow_up" => "upcoming"]),
        );

        $dueResponse
            ->assertOk()
            ->assertSee("FollowDuePastCo")
            ->assertSee("FollowDueTodayCo")
            ->assertDontSee("FollowLaterCo")
            ->assertDontSee("FollowWithoutDateCo");

        $upcomingResponse
            ->assertOk()
            ->assertSee("FollowLaterCo")
            ->assertDontSee("FollowDuePastCo")
            ->assertDontSee("FollowDueTodayCo")
            ->assertDontSee("FollowWithoutDateCo");

        expect($dueResponse->viewData("companies")->pluck("id")->all())
            ->toContain($duePast->id, $dueToday->id)
            ->and($upcomingResponse->viewData("companies")->pluck("id")->all())
            ->toContain($upcoming->id);
    } finally {
        Carbon::setTestNow();
    }
});

test(
    "index supports sorting by whitelisted fields and falls back on invalid sort input",
    function () {
        $user = User::factory()->create();

        $beta = Company::factory()
            ->for($user)
            ->create([
                "name" => "Beta Co",
                "updated_at" => Carbon::parse("2026-01-01 10:00:00"),
            ]);

        $alpha = Company::factory()
            ->for($user)
            ->create([
                "name" => "Alpha Co",
                "updated_at" => Carbon::parse("2026-01-02 10:00:00"),
            ]);

        $sortedResponse = $this->actingAs($user)->get(
            route("companies.index", [
                "sort" => "name",
                "direction" => "asc",
            ]),
        );

        $fallbackResponse = $this->actingAs($user)->get(
            route("companies.index", [
                "sort" => "not-allowed",
                "direction" => "sideways",
            ]),
        );

        $sortedIds = $sortedResponse
            ->viewData("companies")
            ->pluck("id")
            ->values()
            ->all();

        $fallbackIds = $fallbackResponse
            ->viewData("companies")
            ->pluck("id")
            ->values()
            ->all();

        expect(array_search($alpha->id, $sortedIds, true))->toBeLessThan(
            array_search($beta->id, $sortedIds, true),
        );

        expect(array_search($alpha->id, $fallbackIds, true))->toBeLessThan(
            array_search($beta->id, $fallbackIds, true),
        );

        $fallbackFilters = $fallbackResponse->viewData("filters");

        expect($fallbackFilters["sort"])
            ->toBe("updated_at")
            ->and($fallbackFilters["direction"])
            ->toBe("desc");
    },
);

test(
    "index sorting by next follow-up keeps nulls last for both directions",
    function () {
        $user = User::factory()->create();

        $soon = Company::factory()
            ->for($user)
            ->create([
                "name" => "Soon",
                "next_follow_up_at" => "2026-02-11",
            ]);

        $later = Company::factory()
            ->for($user)
            ->create([
                "name" => "Later",
                "next_follow_up_at" => "2026-02-18",
            ]);

        $noDate = Company::factory()
            ->for($user)
            ->create([
                "name" => "No Date",
                "next_follow_up_at" => null,
            ]);

        $ascResponse = $this->actingAs($user)->get(
            route("companies.index", [
                "sort" => "next_follow_up_at",
                "direction" => "asc",
            ]),
        );

        $descResponse = $this->actingAs($user)->get(
            route("companies.index", [
                "sort" => "next_follow_up_at",
                "direction" => "desc",
            ]),
        );

        $ascIds = $ascResponse
            ->viewData("companies")
            ->pluck("id")
            ->values()
            ->all();
        $descIds = $descResponse
            ->viewData("companies")
            ->pluck("id")
            ->values()
            ->all();

        expect(array_search($soon->id, $ascIds, true))->toBeLessThan(
            array_search($later->id, $ascIds, true),
        );

        expect(array_search($later->id, $ascIds, true))->toBeLessThan(
            array_search($noDate->id, $ascIds, true),
        );

        expect(array_search($later->id, $descIds, true))->toBeLessThan(
            array_search($soon->id, $descIds, true),
        );

        expect(array_search($soon->id, $descIds, true))->toBeLessThan(
            array_search($noDate->id, $descIds, true),
        );
    },
);

test("store sanitizes and normalizes incoming payload", function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(
        route("companies.store"),
        companyPayload([
            "name" => "  <b>Acme Sanitized</b>  ",
            "status" => "LEAD",
            "email" => "  SALES@EXAMPLE.COM ",
            "billing_email" => " BILLING@EXAMPLE.COM ",
            "primary_contact_email" => " PRIMARY@EXAMPLE.COM ",
            "website" => "example.org",
            "linkedin_url" => "linkedin.com/company/acme",
            "preferred_contact_method" => "EMAIL",
            "notes" =>
                "<script>alert(1)</script>  Important account\r\nSecond line  ",
        ]),
    );

    $company = Company::query()->firstOrFail();

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route("companies.show", $company))
        ->assertSessionHas("status", "Company created successfully.");

    expect($company->user_id)
        ->toBe($user->id)
        ->and($company->name)
        ->toBe("Acme Sanitized")
        ->and($company->status)
        ->toBe("lead")
        ->and($company->email)
        ->toBe("sales@example.com")
        ->and($company->billing_email)
        ->toBe("billing@example.com")
        ->and($company->primary_contact_email)
        ->toBe("primary@example.com")
        ->and($company->website)
        ->toBe("https://example.org")
        ->and($company->linkedin_url)
        ->toBe("https://linkedin.com/company/acme")
        ->and($company->preferred_contact_method)
        ->toBe("email")
        ->and($company->notes)
        ->toContain("Important account")
        ->and($company->notes)
        ->toContain("Second line")
        ->and($company->notes)
        ->not->toContain("<script>");
});

test(
    "companies routes use dedicated read/write throttle middleware",
    function () {
        $indexRoute = app("router")->getRoutes()->getByName("companies.index");
        $createRoute = app("router")
            ->getRoutes()
            ->getByName("companies.create");
        $showRoute = app("router")->getRoutes()->getByName("companies.show");
        $editRoute = app("router")->getRoutes()->getByName("companies.edit");
        $storeRoute = app("router")->getRoutes()->getByName("companies.store");
        $updateRoute = app("router")
            ->getRoutes()
            ->getByName("companies.update");
        $destroyRoute = app("router")
            ->getRoutes()
            ->getByName("companies.destroy");

        expect($indexRoute?->gatherMiddleware())
            ->toContain("throttle:companies-read")
            ->and($createRoute?->gatherMiddleware())
            ->toContain("throttle:companies-read")
            ->and($showRoute?->gatherMiddleware())
            ->toContain("throttle:companies-read")
            ->and($editRoute?->gatherMiddleware())
            ->toContain("throttle:companies-read")
            ->and($storeRoute?->gatherMiddleware())
            ->toContain("throttle:companies-write")
            ->and($updateRoute?->gatherMiddleware())
            ->toContain("throttle:companies-write")
            ->and($destroyRoute?->gatherMiddleware())
            ->toContain("throttle:companies-write")
            ->and($storeRoute?->gatherMiddleware())
            ->not->toContain("throttle:companies-read")
            ->and($indexRoute?->gatherMiddleware())
            ->not->toContain("throttle:companies-write");
    },
);

test(
    "authenticated users can render the create company page with metadata",
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route("companies.create"));

        $response
            ->assertOk()
            ->assertSee("Create Company")
            ->assertSee("Company Profile")
            ->assertSee("Preferred contact method");

        expect($response->viewData("statuses"))
            ->toBe(Company::statuses())
            ->and($response->viewData("preferredContactMethods"))
            ->toBe(Company::preferredContactMethods());
    },
);

test(
    "users can create companies and data is always assigned to the authenticated user",
    function () {
        $user = User::factory()->create();

        $payload = companyPayload([
            "name" => "Acme Corp",
            "status" => "lead",
            "is_active" => "1",
        ]);

        $response = $this->actingAs($user)->post(
            route("companies.store"),
            $payload,
        );

        $company = Company::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route("companies.show", $company))
            ->assertSessionHas("status", "Company created successfully.");

        expect($company->user_id)
            ->toBe($user->id)
            ->and($company->name)
            ->toBe("Acme Corp")
            ->and($company->status)
            ->toBe("lead")
            ->and($company->is_active)
            ->toBeTrue()
            ->and(Company::query()->count())
            ->toBe(1);
    },
);

test("company creation rejects user_id injection attempts", function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post(
            route("companies.store"),
            companyPayload([
                "name" => "Injection Co",
                "user_id" => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(["user_id"]);

    expect(Company::query()->where("name", "Injection Co")->exists())
        ->toBeFalse()
        ->and(Company::query()->count())
        ->toBe(0);
});

test("company creation validates required and constrained fields", function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(
        route("companies.store"),
        companyPayload([
            "name" => "",
            "status" => "invalid-status",
            "email" => "invalid-email",
            "website" => "not-a-url",
            "annual_revenue" => -1,
            "founded_year" => 1500,
            "employee_count" => 0,
            "phone" => "abc",
            "next_follow_up_at" => "2026-01-01",
            "last_contacted_at" => "2026-02-01",
            "is_active" => "maybe",
        ]),
    );

    $response->assertSessionHasErrors([
        "name",
        "status",
        "email",
        "website",
        "annual_revenue",
        "founded_year",
        "employee_count",
        "phone",
        "next_follow_up_at",
        "is_active",
    ]);

    expect(Company::query()->count())->toBe(0);
});

test(
    "company name must be unique per user but may be reused across users",
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Company::factory()
            ->for($user)
            ->create(["name" => "Shared Name"]);

        $this->actingAs($user)
            ->post(
                route("companies.store"),
                companyPayload(["name" => "Shared Name"]),
            )
            ->assertSessionHasErrors(["name"]);

        $this->actingAs($otherUser)
            ->post(
                route("companies.store"),
                companyPayload(["name" => "Shared Name"]),
            )
            ->assertSessionHasNoErrors();

        expect(Company::query()->where("name", "Shared Name")->count())
            ->toBe(2)
            ->and(
                Company::query()
                    ->where("user_id", $user->id)
                    ->where("name", "Shared Name")
                    ->count(),
            )
            ->toBe(1)
            ->and(
                Company::query()
                    ->where("user_id", $otherUser->id)
                    ->where("name", "Shared Name")
                    ->count(),
            )
            ->toBe(1);
    },
);

test("owners can view and edit their company records", function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create([
            "name" => "Owner Co",
            "industry" => "Finance",
            "status" => "customer",
            "is_active" => false,
            "annual_revenue" => 3400000.12,
        ]);

    $showResponse = $this->actingAs($user)->get(
        route("companies.show", $company),
    );

    $showResponse
        ->assertOk()
        ->assertSee("Owner Co")
        ->assertSee("Finance")
        ->assertSee("Inactive")
        ->assertSee("Customer")
        ->assertSee("Company Information")
        ->assertSee("Contact Information");

    expect($showResponse->viewData("company")->is($company))->toBeTrue();

    $editResponse = $this->actingAs($user)->get(
        route("companies.edit", $company),
    );

    $editResponse
        ->assertOk()
        ->assertSee("Edit Company")
        ->assertSee("Owner Co")
        ->assertSee("Save Changes");

    expect($editResponse->viewData("company")->is($company))
        ->toBeTrue()
        ->and($editResponse->viewData("statuses"))
        ->toBe(Company::statuses())
        ->and($editResponse->viewData("preferredContactMethods"))
        ->toBe(Company::preferredContactMethods());
});

test("non-owners cannot view or edit another users company", function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $company = Company::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route("companies.show", $company))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->get(route("companies.edit", $company))
        ->assertNotFound();
});

test("owners can update their companies with sanitized values", function () {
    $user = User::factory()->create();

    $company = Company::factory()
        ->for($user)
        ->create([
            "name" => "Before Update",
            "status" => "lead",
            "email" => "before@example.com",
            "is_active" => true,
            "notes" => "Original note",
        ]);

    $payload = companyPayload([
        "name" => "  <b>After Update</b>  ",
        "status" => "CUSTOMER",
        "email" => "  AFTER@EXAMPLE.COM ",
        "billing_email" => " BILLING+UPDATED@EXAMPLE.COM ",
        "primary_contact_email" => " PRIMARY+UPDATED@EXAMPLE.COM ",
        "website" => "updated.example.com",
        "linkedin_url" => "linkedin.com/company/after-update",
        "preferred_contact_method" => "PHONE",
        "notes" => "<script>bad()</script> Updated note\r\nLine two",
        "is_active" => "0",
    ]);

    $response = $this->actingAs($user)->put(
        route("companies.update", $company),
        $payload,
    );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route("companies.show", $company))
        ->assertSessionHas("status", "Company updated successfully.");

    $company->refresh();

    expect($company->name)
        ->toBe("After Update")
        ->and($company->status)
        ->toBe("customer")
        ->and($company->email)
        ->toBe("after@example.com")
        ->and($company->billing_email)
        ->toBe("billing+updated@example.com")
        ->and($company->primary_contact_email)
        ->toBe("primary+updated@example.com")
        ->and($company->website)
        ->toBe("https://updated.example.com")
        ->and($company->linkedin_url)
        ->toBe("https://linkedin.com/company/after-update")
        ->and($company->preferred_contact_method)
        ->toBe("phone")
        ->and($company->notes)
        ->toContain("Updated note")
        ->and($company->notes)
        ->toContain("Line two")
        ->and($company->notes)
        ->not->toContain("<script>")
        ->and($company->is_active)
        ->toBeFalse()
        ->and($company->user_id)
        ->toBe($user->id);
});

test(
    "update validates uniqueness while allowing unchanged names and cross-user reuse",
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $existing = Company::factory()
            ->for($user)
            ->create(["name" => "Existing Name"]);

        $target = Company::factory()
            ->for($user)
            ->create(["name" => "Target Name"]);

        Company::factory()
            ->for($otherUser)
            ->create(["name" => "Shared Elsewhere"]);

        $this->actingAs($user)
            ->put(
                route("companies.update", $target),
                companyPayload(["name" => "Existing Name"]),
            )
            ->assertSessionHasErrors(["name"]);

        $this->actingAs($user)
            ->put(
                route("companies.update", $target),
                companyPayload(["name" => "Shared Elsewhere"]),
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(
                route("companies.update", $target),
                companyPayload(["name" => "Target Name"]),
            )
            ->assertSessionHasNoErrors();

        expect($existing->refresh()->name)
            ->toBe("Existing Name")
            ->and($target->refresh()->name)
            ->toBe("Target Name");
    },
);

test("owners cannot change company ownership during update", function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $company = Company::factory()
        ->for($owner)
        ->create(["name" => "Ownership Locked"]);

    $this->actingAs($owner)
        ->put(
            route("companies.update", $company),
            companyPayload([
                "name" => "Ownership Locked",
                "user_id" => $otherUser->id,
            ]),
        )
        ->assertSessionHasErrors(["user_id"]);

    expect($company->fresh()->user_id)
        ->toBe($owner->id)
        ->and($company->fresh()->name)
        ->toBe("Ownership Locked");
});

test("non-owners cannot update another users company", function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $company = Company::factory()
        ->for($owner)
        ->create(["name" => "Protected Co"]);

    $this->actingAs($intruder)
        ->put(
            route("companies.update", $company),
            companyPayload([
                "name" => "Hacked Co",
            ]),
        )
        ->assertNotFound();

    expect($company->fresh()->name)->toBe("Protected Co");
});

test("owners can delete their companies", function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route("companies.destroy", $company))
        ->assertRedirect(route("companies.index"))
        ->assertSessionHas("status", "Company deleted successfully.");

    $this->assertDatabaseMissing("companies", [
        "id" => $company->id,
    ]);

    expect(Company::query()->count())->toBe(0);
});

test("non-owners cannot delete another users company", function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $company = Company::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->delete(route("companies.destroy", $company))
        ->assertNotFound();

    $this->assertDatabaseHas("companies", [
        "id" => $company->id,
        "user_id" => $owner->id,
    ]);
});

test(
    "index shows an empty-state message when there are no companies",
    function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route("companies.index"));

        $response
            ->assertOk()
            ->assertSee(
                "No companies found with the current search/filter settings.",
            )
            ->assertSee("Create your first company");

        expect($response->viewData("companies")->total())->toBe(0);
    },
);
