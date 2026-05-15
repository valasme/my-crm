<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;

class SyncRelationshipTimeline
{
    public function syncCompany(int $userId, ?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $latestCompletedActivityAt = Activity::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->max('activity_at');

        $taskSnapshot = Task::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->selectRaw(
                'max(case when status = ? then task_at end) as latest_completed_task_at',
                ['completed'],
            )
            ->selectRaw(
                'min(case when status = ? and is_active = ? and next_follow_up_at is not null then next_follow_up_at end) as earliest_planned_follow_up_at',
                ['planned', true],
            )
            ->first();

        $latestCompletedTaskAt = $taskSnapshot?->latest_completed_task_at;
        $nextFollowUpAt = $taskSnapshot?->earliest_planned_follow_up_at;

        Company::query()
            ->where('user_id', $userId)
            ->whereKey($companyId)
            ->update([
                'last_contacted_at' => $this->latestDate(
                    $this->toDateString($latestCompletedActivityAt),
                    $this->toDateString($latestCompletedTaskAt),
                ),
                'next_follow_up_at' => $this->toDateString($nextFollowUpAt),
            ]);
    }

    public function syncContact(int $userId, ?int $contactId): void
    {
        if ($contactId === null) {
            return;
        }

        $latestCompletedActivityAt = Activity::query()
            ->where('user_id', $userId)
            ->where('contact_id', $contactId)
            ->where('status', 'completed')
            ->max('activity_at');

        $taskSnapshot = Task::query()
            ->where('user_id', $userId)
            ->where('contact_id', $contactId)
            ->selectRaw(
                'max(case when status = ? then task_at end) as latest_completed_task_at',
                ['completed'],
            )
            ->selectRaw(
                'min(case when status = ? and is_active = ? and next_follow_up_at is not null then next_follow_up_at end) as earliest_planned_follow_up_at',
                ['planned', true],
            )
            ->first();

        $latestCompletedTaskAt = $taskSnapshot?->latest_completed_task_at;
        $nextFollowUpAt = $taskSnapshot?->earliest_planned_follow_up_at;

        Contact::query()
            ->where('user_id', $userId)
            ->whereKey($contactId)
            ->update([
                'last_contacted_at' => $this->latestDate(
                    $this->toDateString($latestCompletedActivityAt),
                    $this->toDateString($latestCompletedTaskAt),
                ),
                'next_follow_up_at' => $this->toDateString($nextFollowUpAt),
            ]);
    }

    private function latestDate(?string $firstDate, ?string $secondDate): ?string
    {
        if ($firstDate === null) {
            return $secondDate;
        }

        if ($secondDate === null) {
            return $firstDate;
        }

        return $firstDate >= $secondDate ? $firstDate : $secondDate;
    }

    private function toDateString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }
}
