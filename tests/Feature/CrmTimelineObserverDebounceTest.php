<?php

use App\Jobs\SyncRelationshipTimelineJob;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

test(
    'task observer debounces duplicate timeline sync jobs for the same relationship set',
    function () {
        Cache::flush();
        Queue::fake();

        $user = User::factory()->create();

        $company = Company::factory()->for($user)->create();

        $contact = Contact::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
            ]);

        $task = Task::factory()
            ->for($user)
            ->create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'status' => 'planned',
                'is_active' => true,
                'task_at' => '2026-01-10',
                'next_follow_up_at' => '2026-01-20',
            ]);

        $task->update([
            'task_at' => '2026-01-11',
        ]);

        Queue::assertPushed(SyncRelationshipTimelineJob::class, 1);
    },
);

test(
    'activity observer debounces duplicate timeline sync jobs for the same relationship set',
    function () {
        Cache::flush();
        Queue::fake();

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
                'status' => 'completed',
                'activity_at' => '2026-01-10',
            ]);

        $activity->update([
            'activity_at' => '2026-01-11',
        ]);

        Queue::assertPushed(SyncRelationshipTimelineJob::class, 1);
    },
);

test(
    'task observer runs timeline sync inline when using sqlite database queue',
    function () {
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.connection', null);
        Cache::flush();

        $user = User::factory()->create();

        $company = Company::factory()
            ->for($user)
            ->create([
                'next_follow_up_at' => null,
            ]);

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
                'next_follow_up_at' => '2026-01-25',
            ]);

        expect($company->fresh()?->next_follow_up_at?->toDateString())->toBe(
            '2026-01-25',
        );
    },
);
