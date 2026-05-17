<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('task create relation selectors support live search filtering', function () {
    $user = User::factory()->create();

    $alphaCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Alpha Filter Co']);

    $zetaCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Zeta Hidden Co']);

    $alphaContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $alphaCompany->id,
            'name' => 'Alpha Filter Contact',
        ]);

    $zetaContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $zetaCompany->id,
            'name' => 'Zeta Hidden Contact',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $alphaCompany->id,
            'contact_id' => $alphaContact->id,
            'name' => 'Alpha Filter Activity',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $zetaCompany->id,
            'contact_id' => $zetaContact->id,
            'name' => 'Zeta Hidden Activity',
        ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::tasks.create')
        ->set('companySearch', 'Alpha');

    expect(array_values($component->instance()->companies))
        ->toContain('Alpha Filter Co')
        ->not->toContain('Zeta Hidden Co');

    $component->set('contactSearch', 'Alpha');

    expect(array_values($component->instance()->contacts))
        ->toContain('Alpha Filter Contact')
        ->not->toContain('Zeta Hidden Contact');

    $component->set('activitySearch', 'Alpha');

    expect(array_values($component->instance()->activities))
        ->toContain('Alpha Filter Activity')
        ->not->toContain('Zeta Hidden Activity');
});

test('task edit relation selectors keep selected options visible while searching', function () {
    $user = User::factory()->create();

    $selectedCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Selected Task Company']);

    $targetCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Target Task Company']);

    $selectedContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $selectedCompany->id,
            'name' => 'Selected Task Contact',
        ]);

    $targetContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $targetCompany->id,
            'name' => 'Target Task Contact',
        ]);

    $selectedActivity = Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $selectedCompany->id,
            'contact_id' => $selectedContact->id,
            'name' => 'Selected Task Activity',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $targetCompany->id,
            'contact_id' => $targetContact->id,
            'name' => 'Target Task Activity',
        ]);

    $task = Task::factory()
        ->for($user)
        ->create([
            'company_id' => $selectedCompany->id,
            'contact_id' => $selectedContact->id,
            'activity_id' => $selectedActivity->id,
        ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::tasks.edit', ['task' => $task])
        ->set('companySearch', 'Target');

    expect(array_values($component->instance()->companies))
        ->toContain('Target Task Company', 'Selected Task Company');

    $component->set('contactSearch', 'Target');

    expect(array_values($component->instance()->contacts))
        ->toContain('Target Task Contact', 'Selected Task Contact');

    $component->set('activitySearch', 'Target');

    expect(array_values($component->instance()->activities))
        ->toContain('Target Task Activity', 'Selected Task Activity');
});
