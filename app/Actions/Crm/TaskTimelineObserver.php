<?php

namespace App\Actions\Crm;

use App\Models\Task;

class TaskTimelineObserver
{
    public function created(Task $task): void
    {
        $this->synchronize($task);
    }

    public function updated(Task $task): void
    {
        if (
            ! $task->wasChanged([
                'company_id',
                'contact_id',
                'status',
                'task_at',
                'next_follow_up_at',
                'is_active',
            ])
        ) {
            return;
        }

        $this->synchronize($task);
    }

    public function deleted(Task $task): void
    {
        $this->synchronize($task);
    }

    private function synchronize(Task $task): void
    {
        $companyIds = $this->normalizeIds([
            $task->company_id,
            $task->getOriginal('company_id'),
        ]);

        $contactIds = $this->normalizeIds([
            $task->contact_id,
            $task->getOriginal('contact_id'),
        ]);

        if ($companyIds === [] && $contactIds === []) {
            return;
        }

        SyncRelationshipTimelineJob::dispatch(
            (int) $task->user_id,
            $companyIds,
            $contactIds,
        );
    }

    /**
     * @param  array<int, int|string|null>  $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
