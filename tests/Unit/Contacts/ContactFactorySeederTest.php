<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ContactSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    'contact factory creates realistic crm records tied to a user',
    function () {
        $contact = Contact::factory()->create();

        expect($contact->user_id)
            ->not->toBeNull()
            ->and($contact->user)
            ->not->toBeNull()
            ->and($contact->user?->id)
            ->toBe($contact->user_id)
            ->and($contact->name)
            ->not->toBe('')
            ->and(Contact::statuses())
            ->toContain($contact->status)
            ->and($contact->is_active === true || $contact->is_active === false)
            ->toBeTrue()
            ->and(
                $contact->preferred_contact_method === null ||
                    in_array(
                        $contact->preferred_contact_method,
                        Contact::preferredContactMethods(),
                        true,
                    ),
            )
            ->toBeTrue()
            ->and(
                $contact->linkedin_url === null ||
                    filter_var($contact->linkedin_url, FILTER_VALIDATE_URL) !==
                        false,
            )
            ->toBeTrue()
            ->and(
                $contact->birthday === null ||
                    ($contact->birthday->year >= now()->year - 80 &&
                        $contact->birthday->year <= now()->year - 18),
            )
            ->toBeTrue();
    },
);

test(
    'contact factory can generate unique contacts for the same user',
    function () {
        $user = User::factory()->create();

        $contacts = Contact::factory()->count(12)->for($user)->create();

        expect($contacts)
            ->toHaveCount(12)
            ->and($contacts->pluck('name')->unique()->count())
            ->toBe(12)
            ->and($contacts->pluck('user_id')->unique()->all())
            ->toBe([$user->id])
            ->and(
                $contacts
                    ->pluck('status')
                    ->diff(Contact::statuses())
                    ->isEmpty(),
            )
            ->toBeTrue()
            ->and($user->fresh()->contacts()->count())
            ->toBe(12);
    },
);

test(
    'contact factory can link contacts to one of the users companies',
    function () {
        $user = User::factory()->create();
        $companies = Company::factory()->count(3)->for($user)->create();

        $contact = Contact::factory()->for($user)->withCompany()->create();

        expect($contact->company_id)
            ->not->toBeNull()
            ->and($companies->pluck('id')->all())
            ->toContain($contact->company_id)
            ->and($contact->company?->user_id)
            ->toBe($user->id);
    },
);

test('contact seeder creates contacts for every existing user', function () {
    $users = User::factory()->count(3)->create();

    $users->each(function (User $user): void {
        Company::factory()->count(2)->for($user)->create();
    });

    $this->seed(ContactSeeder::class);

    expect(Contact::query()->count())->toBe($users->count() * 50);

    $users->each(function (User $user): void {
        $contacts = $user->fresh()->contacts;

        expect($contacts)
            ->toHaveCount(50)
            ->and($contacts->pluck('user_id')->unique()->all())
            ->toBe([$user->id])
            ->and($contacts->pluck('name')->unique()->count())
            ->toBe(50)
            ->and(
                $contacts
                    ->pluck('status')
                    ->diff(Contact::statuses())
                    ->isEmpty(),
            )
            ->toBeTrue();
    });

    expect(
        Contact::query()->pluck('status')->diff(Contact::statuses())->isEmpty(),
    )->toBeTrue();
});

test(
    'contact seeder normalizes each user to the target count and remains idempotent',
    function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        $userD = User::factory()->create();

        collect([$userA, $userB, $userC, $userD])->each(function (
            User $user,
        ): void {
            Company::factory()->count(2)->for($user)->create();
        });

        Contact::factory()->count(3)->for($userA)->create();
        Contact::factory()->count(50)->for($userB)->create();

        $overSeeded = Contact::factory()->count(51)->for($userC)->create();
        $overSeededIds = $overSeeded->pluck('id')->all();

        $this->seed(ContactSeeder::class);

        expect($userA->fresh()->contacts()->count())
            ->toBe(50)
            ->and($userB->fresh()->contacts()->count())
            ->toBe(50)
            ->and($userC->fresh()->contacts()->count())
            ->toBe(50)
            ->and($userD->fresh()->contacts()->count())
            ->toBe(50)
            ->and(Contact::query()->count())
            ->toBe(200)
            ->and(Contact::query()->whereIn('id', $overSeededIds)->count())
            ->toBe(count($overSeededIds) - 1);

        collect([$userA, $userB, $userC, $userD])->each(function (
            User $user,
        ): void {
            $contacts = $user->fresh()->contacts;

            expect($contacts->pluck('name')->unique()->count())->toBe(
                $contacts->count(),
            );
        });

        $this->seed(ContactSeeder::class);

        expect($userA->fresh()->contacts()->count())
            ->toBe(50)
            ->and($userB->fresh()->contacts()->count())
            ->toBe(50)
            ->and($userC->fresh()->contacts()->count())
            ->toBe(50)
            ->and($userD->fresh()->contacts()->count())
            ->toBe(50)
            ->and(Contact::query()->count())
            ->toBe(200);
    },
);

test(
    'contact seeder does not create contacts when there are no users',
    function () {
        expect(User::query()->count())
            ->toBe(0)
            ->and(Contact::query()->count())
            ->toBe(0);

        $this->seed(ContactSeeder::class);
        $this->seed(ContactSeeder::class);

        expect(User::query()->count())
            ->toBe(0)
            ->and(Contact::query()->count())
            ->toBe(0);
    },
);
