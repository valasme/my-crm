<?php

namespace App\Actions\Crm;

use App\Models\Activity;

class ActivityTimelineObserver
{
    public function created(Activity $activity): void
    {
        $this->synchronize($activity);
    }

    public function updated(Activity $activity): void
    {
        if (
            ! $activity->wasChanged([
                'company_id',
                'contact_id',
                'status',
                'activity_at',
            ])
        ) {
            return;
        }

        $this->synchronize($activity);
    }

    public function deleted(Activity $activity): void
    {
        $this->synchronize($activity);
    }

    private function synchronize(Activity $activity): void
    {
        $companyIds = $this->normalizeIds([
            $activity->company_id,
            $activity->getOriginal('company_id'),
        ]);

        $contactIds = $this->normalizeIds([
            $activity->contact_id,
            $activity->getOriginal('contact_id'),
        ]);

        if ($companyIds === [] && $contactIds === []) {
            return;
        }

        SyncRelationshipTimelineJob::dispatch(
            (int) $activity->user_id,
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
