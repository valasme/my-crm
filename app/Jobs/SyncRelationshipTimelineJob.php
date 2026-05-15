<?php

namespace App\Jobs;

use App\Actions\SyncRelationshipTimeline;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

#[Tries(5)]
#[Backoff([1, 5, 10])]
#[UniqueFor(8)]
class SyncRelationshipTimelineJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $contactIds
     */
    public function __construct(
        public int $userId,
        public array $companyIds = [],
        public array $contactIds = [],
    ) {
        $this->afterCommit();

        $this->companyIds = $this->normalizeIds($companyIds);
        $this->contactIds = $this->normalizeIds($contactIds);
    }

    public function uniqueId(): string
    {
        return sprintf(
            'crm:timeline-sync:%d:%s:%s',
            $this->userId,
            implode(',', $this->companyIds),
            implode(',', $this->contactIds),
        );
    }

    public function handle(SyncRelationshipTimeline $syncRelationshipTimeline): void
    {
        foreach ($this->companyIds as $companyId) {
            $syncRelationshipTimeline->syncCompany(
                userId: $this->userId,
                companyId: $companyId,
            );
        }

        foreach ($this->contactIds as $contactId) {
            $syncRelationshipTimeline->syncContact(
                userId: $this->userId,
                contactId: $contactId,
            );
        }
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
}
