<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    'company factory creates realistic crm records tied to a user',
    function () {
        $company = Company::factory()->create();

        expect($company->user_id)
            ->not->toBeNull()
            ->and($company->user)
            ->not->toBeNull()
            ->and($company->user?->id)
            ->toBe($company->user_id)
            ->and($company->name)
            ->not->toBe('')
            ->and(Company::statuses())
            ->toContain($company->status)
            ->and($company->is_active === true || $company->is_active === false)
            ->toBeTrue()
            ->and(
                $company->founded_year === null ||
                    ($company->founded_year >= 1950 &&
                        $company->founded_year <= now()->year),
            )
            ->toBeTrue()
            ->and(
                $company->employee_count === null ||
                    $company->employee_count >= 1,
            )
            ->toBeTrue()
            ->and(
                $company->annual_revenue === null ||
                    (float) $company->annual_revenue >= 0,
            )
            ->toBeTrue()
            ->and(
                $company->preferred_contact_method === null ||
                    in_array(
                        $company->preferred_contact_method,
                        Company::preferredContactMethods(),
                        true,
                    ),
            )
            ->toBeTrue()
            ->and(
                $company->website === null ||
                    filter_var($company->website, FILTER_VALIDATE_URL) !==
                        false,
            )
            ->toBeTrue()
            ->and(
                $company->linkedin_url === null ||
                    filter_var($company->linkedin_url, FILTER_VALIDATE_URL) !==
                        false,
            )
            ->toBeTrue();
    },
);

test(
    'company factory can generate unique companies for the same user',
    function () {
        $user = User::factory()->create();

        $companies = Company::factory()->count(12)->for($user)->create();

        expect($companies)
            ->toHaveCount(12)
            ->and($companies->pluck('name')->unique()->count())
            ->toBe(12)
            ->and($companies->pluck('user_id')->unique()->all())
            ->toBe([$user->id])
            ->and(
                $companies
                    ->pluck('status')
                    ->diff(Company::statuses())
                    ->isEmpty(),
            )
            ->toBeTrue()
            ->and($user->fresh()->companies()->count())
            ->toBe(12);
    },
);

test('company seeder creates companies for every existing user', function () {
    $users = User::factory()->count(3)->create();

    $this->seed(CompanySeeder::class);

    expect(Company::query()->count())->toBe($users->count() * 50);

    $users->each(function (User $user): void {
        $companies = $user->fresh()->companies;

        expect($companies)
            ->toHaveCount(50)
            ->and($companies->pluck('user_id')->unique()->all())
            ->toBe([$user->id])
            ->and($companies->pluck('name')->unique()->count())
            ->toBe(50)
            ->and(
                $companies
                    ->pluck('status')
                    ->diff(Company::statuses())
                    ->isEmpty(),
            )
            ->toBeTrue();
    });

    expect(
        Company::query()->pluck('status')->diff(Company::statuses())->isEmpty(),
    )->toBeTrue();
});

test(
    'company seeder normalizes each user to the target count and remains idempotent',
    function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $userD = User::factory()->create();

        Company::factory()->count(3)->for($userA)->create();
        Company::factory()->count(50)->for($userB)->create();

        $overSeeded = Company::factory()->count(51)->for($userC)->create();
        $overSeededIds = $overSeeded->pluck('id')->all();

        $this->seed(CompanySeeder::class);

        expect($userA->fresh()->companies()->count())
            ->toBe(50)
            ->and($userB->fresh()->companies()->count())
            ->toBe(50)
            ->and($userC->fresh()->companies()->count())
            ->toBe(50)
            ->and($userD->fresh()->companies()->count())
            ->toBe(50)
            ->and(Company::query()->count())
            ->toBe(200)
            ->and(Company::query()->whereIn('id', $overSeededIds)->count())
            ->toBe(count($overSeededIds) - 1);

        collect([$userA, $userB, $userC, $userD])->each(function (
            User $user,
        ): void {
            $companies = $user->fresh()->companies;

            expect($companies->pluck('name')->unique()->count())->toBe(
                $companies->count(),
            );
        });

        $this->seed(CompanySeeder::class);

        expect($userA->fresh()->companies()->count())
            ->toBe(50)
            ->and($userB->fresh()->companies()->count())
            ->toBe(50)
            ->and($userC->fresh()->companies()->count())
            ->toBe(50)
            ->and($userD->fresh()->companies()->count())
            ->toBe(50)
            ->and(Company::query()->count())
            ->toBe(200);
    },
);

test(
    'company seeder does not create companies when there are no users',
    function () {
        expect(User::query()->count())
            ->toBe(0)
            ->and(Company::query()->count())
            ->toBe(0);

        $this->seed(CompanySeeder::class);
        $this->seed(CompanySeeder::class);

        expect(User::query()->count())
            ->toBe(0)
            ->and(Company::query()->count())
            ->toBe(0);
    },
);
