<?php

namespace App\Actions\Crm;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncRelationshipTimelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, int|string|null>  $companyIds
     * @param  array<int, int|string|null>  $contactIds
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

    public function handle(
        SyncRelationshipTimeline $syncRelationshipTimeline,
    ): void {
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
            ->values()
            ->all();
    }
}
