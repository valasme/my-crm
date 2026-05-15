<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('sidebar renders global search trigger', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Global Search')
        ->assertSee('global-search-modal', false);
});

test(
    'global search modal finds matching records across crm entities',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create([
                'name' => 'Nebula Search Company',
                'status' => 'customer',
                'industry' => 'Technology',
            ]);

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'name' => 'Nebula Contact',
                'job_title' => 'Head of Partnerships',
                'email' => 'nebula.contact@example.test',
            ]);

        $activity = Activity::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'name' => 'Nebula Activity',
                'type' => 'meeting',
                'status' => 'planned',
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'activity_id' => $activity->id,
                'name' => 'Nebula Task',
                'type' => 'call',
                'status' => 'planned',
            ]);

        Deal::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'activity_id' => $activity->id,
                'name' => 'Nebula Deal',
                'status' => 'proposal',
                'currency' => 'USD',
                'amount' => 25000,
            ]);

        Company::factory()
            ->for($otherUser)
            ->create([
                'name' => 'Nebula Hidden Company',
            ]);

        $this->actingAs($user);

        Livewire::test('global-search-modal')
            ->set('query', 'Nebula')
            ->assertSee('Companies')
            ->assertSee('Contacts')
            ->assertSee('Activities')
            ->assertSee('Tasks')
            ->assertSee('Deals')
            ->assertSee('Nebula Search Company')
            ->assertSee('Nebula Contact')
            ->assertSee('Nebula Activity')
            ->assertSee('Nebula Task')
            ->assertSee('Nebula Deal')
            ->assertDontSee('Nebula Hidden Company');
    },
);

test('global search requires at least two characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('global-search-modal')
        ->set('query', 'N')
        ->assertSee('Type at least 2 characters to start searching.');
});
