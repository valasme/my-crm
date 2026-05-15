<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('activities table has timeline sync aggregate indexes', function () {
    expect(
        Schema::hasIndex(
            'activities',
            'activities_user_company_status_activity_at_index',
        ),
    )
        ->toBeTrue()
        ->and(
            Schema::hasIndex('activities', [
                'user_id',
                'company_id',
                'status',
                'activity_at',
            ]),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex(
                'activities',
                'activities_user_contact_status_activity_at_index',
            ),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex('activities', [
                'user_id',
                'contact_id',
                'status',
                'activity_at',
            ]),
        )
        ->toBeTrue();
});

test('tasks table has timeline sync aggregate indexes', function () {
    expect(Schema::hasIndex('tasks', 'tasks_user_company_status_task_at_index'))
        ->toBeTrue()
        ->and(
            Schema::hasIndex('tasks', [
                'user_id',
                'company_id',
                'status',
                'task_at',
            ]),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex(
                'tasks',
                'tasks_user_contact_status_task_at_index',
            ),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex('tasks', [
                'user_id',
                'contact_id',
                'status',
                'task_at',
            ]),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex(
                'tasks',
                'tasks_user_company_status_active_follow_up_index',
            ),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex('tasks', [
                'user_id',
                'company_id',
                'status',
                'is_active',
                'next_follow_up_at',
            ]),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex(
                'tasks',
                'tasks_user_contact_status_active_follow_up_index',
            ),
        )
        ->toBeTrue()
        ->and(
            Schema::hasIndex('tasks', [
                'user_id',
                'contact_id',
                'status',
                'is_active',
                'next_follow_up_at',
            ]),
        )
        ->toBeTrue();
});
