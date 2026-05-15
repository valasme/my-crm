<?php

use App\Jobs\SyncRelationshipTimelineJob;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function crmTaskPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->sentence(3),
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Inbound',
            'task_at' => '2026-01-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => '1',
            'outcome' => 'Expected outcome',
            'notes' => 'Task notes',
        ],
        $overrides,
    );
}

function crmActivityPayload(array $overrides = []): array
{
    return array_merge(
        [
            'name' => fake()->unique()->sentence(3),
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Inbound',
            'activity_at' => '2026-01-10',
            'notes' => 'Activity notes',
        ],
        $overrides,
    );
}

test(
    'task creation queues timeline synchronization job with related ids',
    function () {
        Queue::fake();

        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
            ]);

        Task::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'status' => 'planned',
                'is_active' => true,
                'task_at' => '2026-01-10',
                'next_follow_up_at' => '2026-01-20',
            ]);

        Queue::assertPushed(
            SyncRelationshipTimelineJob::class,
            fn (SyncRelationshipTimelineJob $job): bool => $job->userId ===
                $user->id &&
                $job->companyIds === [$company->id] &&
                $job->contactIds === [$contact->id],
        );
    },
);

test(
    'task creation auto-fills company from contact and syncs follow-up dates',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create([
                'next_follow_up_at' => null,
                'last_contacted_at' => null,
            ]);

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'next_follow_up_at' => null,
                'last_contacted_at' => null,
            ]);

        $response = $this->actingAs($user)->post(
            route('tasks.store'),
            crmTaskPayload([
                'company_id' => null,
                'contact_id' => $contact->id,
                'next_follow_up_at' => '2026-02-05',
            ]),
        );

        $task = Task::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', $task));

        expect($task->company_id)
            ->toBe($company->id)
            ->and($task->contact_id)
            ->toBe($contact->id)
            ->and($company->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-02-05')
            ->and($contact->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-02-05')
            ->and($company->fresh()?->last_contacted_at)
            ->toBeNull()
            ->and($contact->fresh()?->last_contacted_at)
            ->toBeNull();
    },
);

test(
    'task creation auto-fills contact and company from related activity',
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

        $response = $this->actingAs($user)->post(
            route('tasks.store'),
            crmTaskPayload([
                'company_id' => null,
                'contact_id' => null,
                'activity_id' => $activity->id,
                'next_follow_up_at' => '2026-03-01',
            ]),
        );

        $task = Task::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tasks.show', $task));

        expect($task->company_id)
            ->toBe($company->id)
            ->and($task->contact_id)
            ->toBe($contact->id)
            ->and($task->activity_id)
            ->toBe($activity->id);
    },
);

test(
    'activity creation auto-fills company from contact and syncs last contacted dates',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create([
                'last_contacted_at' => null,
                'next_follow_up_at' => null,
            ]);

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'last_contacted_at' => null,
                'next_follow_up_at' => null,
            ]);

        $response = $this->actingAs($user)->post(
            route('activities.store'),
            crmActivityPayload([
                'company_id' => null,
                'contact_id' => $contact->id,
                'status' => 'completed',
                'activity_at' => '2026-02-10',
            ]),
        );

        $activity = Activity::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('activities.show', $activity));

        expect($activity->company_id)
            ->toBe($company->id)
            ->and($activity->contact_id)
            ->toBe($contact->id)
            ->and($company->fresh()?->last_contacted_at?->toDateString())
            ->toBe('2026-02-10')
            ->and($contact->fresh()?->last_contacted_at?->toDateString())
            ->toBe('2026-02-10');
    },
);

test(
    'timeline sync recalculates oldest and newest relations after task reassignment and deletion',
    function () {
        $user = User::factory()->create();

        $companyA = Company::factory()->for($user)->create();
        $companyB = Company::factory()->for($user)->create();

        $contactA = Contact::factory()
            ->for($user)
            ->create(['company_id' => $companyA->id]);
        $contactB = Contact::factory()
            ->for($user)
            ->create(['company_id' => $companyB->id]);

        $firstTask = Task::factory()
            ->for($user)
            ->create([
                'company_id' => $companyA->id,
                'contact_id' => $contactA->id,
                'status' => 'planned',
                'is_active' => true,
                'task_at' => '2026-01-10',
                'next_follow_up_at' => '2026-01-20',
            ]);

        $secondTask = Task::factory()
            ->for($user)
            ->create([
                'company_id' => $companyA->id,
                'contact_id' => $contactA->id,
                'status' => 'planned',
                'is_active' => true,
                'task_at' => '2026-01-11',
                'next_follow_up_at' => '2026-01-15',
            ]);

        expect($companyA->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-01-15')
            ->and($contactA->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-01-15');

        $secondTask->update([
            'company_id' => $companyB->id,
            'contact_id' => $contactB->id,
            'next_follow_up_at' => '2026-02-05',
        ]);

        expect($companyA->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-01-20')
            ->and($contactA->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-01-20')
            ->and($companyB->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-02-05')
            ->and($contactB->fresh()?->next_follow_up_at?->toDateString())
            ->toBe('2026-02-05');

        $firstTask->delete();

        expect($companyA->fresh()?->next_follow_up_at)
            ->toBeNull()
            ->and($contactA->fresh()?->next_follow_up_at)
            ->toBeNull();
    },
);

test(
    'last contacted sync uses latest completed record across activities and tasks',
    function () {
        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create(['company_id' => $company->id]);

        $activity = Activity::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'status' => 'completed',
                'activity_at' => '2026-01-10',
            ]);

        $task = Task::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'status' => 'completed',
                'is_active' => false,
                'task_at' => '2026-01-12',
                'next_follow_up_at' => null,
            ]);

        expect($company->fresh()?->last_contacted_at?->toDateString())
            ->toBe('2026-01-12')
            ->and($contact->fresh()?->last_contacted_at?->toDateString())
            ->toBe('2026-01-12');

        $task->delete();

        expect($company->fresh()?->last_contacted_at?->toDateString())
            ->toBe('2026-01-10')
            ->and($contact->fresh()?->last_contacted_at?->toDateString())
            ->toBe('2026-01-10');

        $activity->delete();

        expect($company->fresh()?->last_contacted_at)
            ->toBeNull()
            ->and($contact->fresh()?->last_contacted_at)
            ->toBeNull();
    },
);
