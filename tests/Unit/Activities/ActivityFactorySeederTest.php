<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    'activity factory creates realistic crm records tied to a user',
    function () {
        $activity = Activity::factory()->create();

        expect($activity->user_id)
            ->not->toBeNull()
            ->and($activity->user)
            ->not->toBeNull()
            ->and($activity->user?->id)
            ->toBe($activity->user_id)
            ->and($activity->name)
            ->not->toBe('')
            ->and(Activity::statuses())
            ->toContain($activity->status)
            ->and(Activity::types())
            ->toContain($activity->type)
            ->and($activity->activity_at)
            ->not->toBeNull()
            ->and(
                $activity->is_active === true || $activity->is_active === false,
            )
            ->toBeTrue();
    },
);

test(
    'activity factory can generate unique activities for the same user',
    function () {
        $user = User::factory()->create();

        $activities = Activity::factory()->count(12)->for($user)->create();

        expect($activities)
            ->toHaveCount(12)
            ->and($activities->pluck('name')->unique()->count())
            ->toBe(12)
            ->and($activities->pluck('user_id')->unique()->all())
            ->toBe([$user->id])
            ->and(
                $activities
                    ->pluck('status')
                    ->diff(Activity::statuses())
                    ->isEmpty(),
            )
            ->toBeTrue()
            ->and(
                $activities->pluck('type')->diff(Activity::types())->isEmpty(),
            )
            ->toBeTrue()
            ->and($user->fresh()->activities()->count())
            ->toBe(12);
    },
);

test(
    'activity factory can link activities to one of the users companies',
    function () {
        $user = User::factory()->create();
        $companies = Company::factory()->count(3)->for($user)->create();

        $activity = Activity::factory()->for($user)->withCompany()->create();

        expect($activity->company_id)
            ->not->toBeNull()
            ->and($companies->pluck('id')->all())
            ->toContain($activity->company_id)
            ->and($activity->company?->user_id)
            ->toBe($user->id);
    },
);

test(
    'activity factory can link activities to one of the users contacts',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();
        $contacts = Contact::factory()
            ->count(3)
            ->for($user)
            ->create(['company_id' => $company->id]);

        $activity = Activity::factory()->for($user)->withContact()->create();

        expect($activity->contact_id)
            ->not->toBeNull()
            ->and($contacts->pluck('id')->all())
            ->toContain($activity->contact_id)
            ->and($activity->contact?->user_id)
            ->toBe($user->id)
            ->and($activity->company_id)
            ->toBe($activity->contact?->company_id);
    },
);

test('activity seeder creates activities for every existing user', function () {
    $users = User::factory()->count(3)->create();

    $users->each(function (User $user): void {
        $companies = Company::factory()->count(2)->for($user)->create();

        Contact::factory()
            ->count(2)
            ->for($user)
            ->create([
                'company_id' => $companies->first()?->id,
            ]);
    });

    $this->seed(ActivitySeeder::class);

    expect(Activity::query()->count())->toBe($users->count() * 50);

    $users->each(function (User $user): void {
        $activities = $user->fresh()->activities;

        expect($activities)
            ->toHaveCount(50)
            ->and($activities->pluck('user_id')->unique()->all())
            ->toBe([$user->id])
            ->and($activities->pluck('name')->unique()->count())
            ->toBe(50)
            ->and(
                $activities
                    ->pluck('status')
                    ->diff(Activity::statuses())
                    ->isEmpty(),
            )
            ->toBeTrue()
            ->and(
                $activities->pluck('type')->diff(Activity::types())->isEmpty(),
            )
            ->toBeTrue();
    });

    expect(
        Activity::query()
            ->pluck('status')
            ->diff(Activity::statuses())
            ->isEmpty(),
    )
        ->toBeTrue()
        ->and(
            Activity::query()
                ->pluck('type')
                ->diff(Activity::types())
                ->isEmpty(),
        )
        ->toBeTrue();
});

test(
    'activity seeder normalizes each user to the target count and remains idempotent',
    function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $userD = User::factory()->create();

        collect([$userA, $userB, $userC, $userD])->each(function (
            User $user,
        ): void {
            $companies = Company::factory()->count(2)->for($user)->create();

            Contact::factory()
                ->count(2)
                ->for($user)
                ->create([
                    'company_id' => $companies->first()?->id,
                ]);
        });

        Activity::factory()->count(3)->for($userA)->create();
        Activity::factory()->count(50)->for($userB)->create();

        $overSeeded = Activity::factory()->count(51)->for($userC)->create();
        $overSeededIds = $overSeeded->pluck('id')->all();

        $this->seed(ActivitySeeder::class);

        expect($userA->fresh()->activities()->count())
            ->toBe(50)
            ->and($userB->fresh()->activities()->count())
            ->toBe(50)
            ->and($userC->fresh()->activities()->count())
            ->toBe(50)
            ->and($userD->fresh()->activities()->count())
            ->toBe(50)
            ->and(Activity::query()->count())
            ->toBe(200)
            ->and(Activity::query()->whereIn('id', $overSeededIds)->count())
            ->toBe(count($overSeededIds) - 1);

        collect([$userA, $userB, $userC, $userD])->each(function (
            User $user,
        ): void {
            $activities = $user->fresh()->activities;

            expect($activities->pluck('name')->unique()->count())->toBe(
                $activities->count(),
            );
        });

        $this->seed(ActivitySeeder::class);

        expect($userA->fresh()->activities()->count())
            ->toBe(50)
            ->and($userB->fresh()->activities()->count())
            ->toBe(50)
            ->and($userC->fresh()->activities()->count())
            ->toBe(50)
            ->and($userD->fresh()->activities()->count())
            ->toBe(50)
            ->and(Activity::query()->count())
            ->toBe(200);
    },
);

test(
    'activity seeder does not create activities when there are no users',
    function () {
        expect(User::query()->count())
            ->toBe(0)
            ->and(Activity::query()->count())
            ->toBe(0);

        $this->seed(ActivitySeeder::class);
        $this->seed(ActivitySeeder::class);

        expect(User::query()->count())
            ->toBe(0)
            ->and(Activity::query()->count())
            ->toBe(0);
    },
);
