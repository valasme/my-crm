<?php

namespace App\Actions\Crm;

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Testing\Fakes\QueueFake;

class ActivityTimelineObserver
{
    private const DISPATCH_DEBOUNCE_SECONDS = 2;

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

        $userId = (int) $activity->user_id;

        if ($this->usesSynchronousDispatch()) {
            $this->synchronizeImmediately($userId, $companyIds, $contactIds);

            return;
        }

        if (! $this->shouldDispatch($userId, $companyIds, $contactIds)) {
            return;
        }

        SyncRelationshipTimelineJob::dispatch(
            $userId,
            $companyIds,
            $contactIds,
        )->delay(now()->addSeconds(self::DISPATCH_DEBOUNCE_SECONDS));
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
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $contactIds
     */
    private function shouldDispatch(
        int $userId,
        array $companyIds,
        array $contactIds,
    ): bool {
        $key = $this->dispatchKey($userId, $companyIds, $contactIds);

        return Cache::add($key, true, self::DISPATCH_DEBOUNCE_SECONDS);
    }

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $contactIds
     */
    private function dispatchKey(
        int $userId,
        array $companyIds,
        array $contactIds,
    ): string {
        return sprintf(
            'crm:timeline-sync:%d:%s:%s',
            $userId,
            implode(',', $companyIds),
            implode(',', $contactIds),
        );
    }

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $contactIds
     */
    private function synchronizeImmediately(
        int $userId,
        array $companyIds,
        array $contactIds,
    ): void {
        $syncRelationshipTimeline = app(SyncRelationshipTimeline::class);

        foreach ($companyIds as $companyId) {
            $syncRelationshipTimeline->syncCompany(
                userId: $userId,
                companyId: $companyId,
            );
        }

        foreach ($contactIds as $contactId) {
            $syncRelationshipTimeline->syncContact(
                userId: $userId,
                contactId: $contactId,
            );
        }
    }

    private function usesSynchronousDispatch(): bool
    {
        $queue = app('queue');

        if ($queue instanceof QueueFake) {
            return false;
        }

        $defaultQueueConnection = (string) config('queue.default');

        if ($defaultQueueConnection === 'sync') {
            return true;
        }

        if ($defaultQueueConnection !== 'database') {
            return false;
        }

        $queueDatabaseConnection = config(
            'queue.connections.database.connection',
        );

        $databaseConnectionName =
            is_string($queueDatabaseConnection) &&
            $queueDatabaseConnection !== ''
                ? $queueDatabaseConnection
                : (string) config('database.default');

        return config(
            "database.connections.{$databaseConnectionName}.driver",
        ) === 'sqlite';
    }
}
