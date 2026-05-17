<?php

use App\Models\Activity;
use App\Models\Deal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;

test('tasks enforce activity ownership at the database layer', function () {
    $activityOwner = User::factory()->create();
    $taskOwner = User::factory()->create();

    $foreignActivity = Activity::factory()->for($activityOwner)->create();

    expect(function () use ($taskOwner, $foreignActivity): void {
        Task::factory()
            ->for($taskOwner)
            ->create([
                'activity_id' => $foreignActivity->id,
                'company_id' => null,
                'contact_id' => null,
            ]);
    })->toThrow(QueryException::class);
});

test('deals enforce activity ownership at the database layer', function () {
    $activityOwner = User::factory()->create();
    $dealOwner = User::factory()->create();

    $foreignActivity = Activity::factory()->for($activityOwner)->create();

    expect(function () use ($dealOwner, $foreignActivity): void {
        Deal::factory()
            ->for($dealOwner)
            ->create([
                'activity_id' => $foreignActivity->id,
                'company_id' => null,
                'contact_id' => null,
            ]);
    })->toThrow(QueryException::class);
});

test('activity deletion still nulls linked task and deal activity references', function () {
    $user = User::factory()->create();

    $activity = Activity::factory()->for($user)->create();

    $task = Task::factory()->for($user)->create([
        'activity_id' => $activity->id,
        'company_id' => null,
        'contact_id' => null,
    ]);

    $deal = Deal::factory()->for($user)->create([
        'activity_id' => $activity->id,
        'company_id' => null,
        'contact_id' => null,
    ]);

    $activity->delete();

    expect($task->fresh()?->activity_id)
        ->toBeNull()
        ->and($deal->fresh()?->activity_id)
        ->toBeNull();
});
