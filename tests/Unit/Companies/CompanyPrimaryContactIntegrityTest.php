<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test(
    'database constraints allow assigning a company primary contact when ownership and company match',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
            ]);

        Company::query()
            ->whereKey($company->id)
            ->update([
                'primary_contact_id' => $contact->id,
            ]);

        expect($company->fresh()?->primary_contact_id)->toBe($contact->id);
    },
);

test(
    'database constraints reject assigning a primary contact from another company',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();
        $otherCompany = Company::factory()->for($user)->create();

        $wrongCompanyContact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $otherCompany->id,
            ]);

        expect(
            fn () => Company::query()
                ->whereKey($company->id)
                ->update([
                    'primary_contact_id' => $wrongCompanyContact->id,
                ]),
        )->toThrow(QueryException::class);
    },
);

test(
    'database constraints reject assigning a primary contact owned by another user',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = Company::factory()->for($user)->create();
        $otherUserCompany = Company::factory()->for($otherUser)->create();

        $otherUserContact = Contact::factory()
            ->for($otherUser)
            ->create([
                'company_id' => $otherUserCompany->id,
            ]);

        expect(
            fn () => Company::query()
                ->whereKey($company->id)
                ->update([
                    'primary_contact_id' => $otherUserContact->id,
                ]),
        )->toThrow(QueryException::class);
    },
);

test(
    'deleting a primary contact still nulls out companies.primary_contact_id',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
            ]);

        Company::query()
            ->whereKey($company->id)
            ->update([
                'primary_contact_id' => $contact->id,
            ]);

        $contact->delete();

        expect($company->fresh()?->primary_contact_id)->toBeNull();
    },
);
