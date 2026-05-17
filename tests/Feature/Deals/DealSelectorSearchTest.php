<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Livewire\Livewire;

test('deal create relation selectors support live search filtering', function () {
    $user = User::factory()->create();

    $alphaCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Alpha Deal Co']);

    $zetaCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Zeta Deal Co']);

    $alphaContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $alphaCompany->id,
            'name' => 'Alpha Deal Contact',
        ]);

    $zetaContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $zetaCompany->id,
            'name' => 'Zeta Deal Contact',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $alphaCompany->id,
            'contact_id' => $alphaContact->id,
            'name' => 'Alpha Deal Activity',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $zetaCompany->id,
            'contact_id' => $zetaContact->id,
            'name' => 'Zeta Deal Activity',
        ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::deals.create')
        ->set('companySearch', 'Alpha');

    expect(array_values($component->instance()->companies))
        ->toContain('Alpha Deal Co')
        ->not->toContain('Zeta Deal Co');

    $component->set('contactSearch', 'Alpha');

    expect(array_values($component->instance()->contacts))
        ->toContain('Alpha Deal Contact')
        ->not->toContain('Zeta Deal Contact');

    $component->set('activitySearch', 'Alpha');

    expect(array_values($component->instance()->activities))
        ->toContain('Alpha Deal Activity')
        ->not->toContain('Zeta Deal Activity');
});

test('deal edit relation selectors keep selected options visible while searching', function () {
    $user = User::factory()->create();

    $selectedCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Selected Deal Company']);

    $targetCompany = Company::factory()
        ->for($user)
        ->create(['name' => 'Target Deal Company']);

    $selectedContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $selectedCompany->id,
            'name' => 'Selected Deal Contact',
        ]);

    $targetContact = Contact::factory()
        ->for($user)
        ->create([
            'company_id' => $targetCompany->id,
            'name' => 'Target Deal Contact',
        ]);

    $selectedActivity = Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $selectedCompany->id,
            'contact_id' => $selectedContact->id,
            'name' => 'Selected Deal Activity',
        ]);

    Activity::factory()
        ->for($user)
        ->create([
            'company_id' => $targetCompany->id,
            'contact_id' => $targetContact->id,
            'name' => 'Target Deal Activity',
        ]);

    $deal = Deal::factory()
        ->for($user)
        ->create([
            'company_id' => $selectedCompany->id,
            'contact_id' => $selectedContact->id,
            'activity_id' => $selectedActivity->id,
        ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::deals.edit', ['deal' => $deal])
        ->set('companySearch', 'Target');

    expect(array_values($component->instance()->companies))
        ->toContain('Target Deal Company', 'Selected Deal Company');

    $component->set('contactSearch', 'Target');

    expect(array_values($component->instance()->contacts))
        ->toContain('Target Deal Contact', 'Selected Deal Contact');

    $component->set('activitySearch', 'Target');

    expect(array_values($component->instance()->activities))
        ->toContain('Target Deal Activity', 'Selected Deal Activity');
});
