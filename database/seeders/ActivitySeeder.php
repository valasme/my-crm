<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * @var int
     */
    private const ACTIVITIES_PER_USER = 50;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::query()
            ->select('id')
            ->withCount('activities')
            ->lazyById(250)
            ->each(function (User $user): void {
                $currentCount = (int) $user->activities_count;

                if ($currentCount > self::ACTIVITIES_PER_USER) {
                    $excessCount = $currentCount - self::ACTIVITIES_PER_USER;

                    $idsToDelete = Activity::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('id')
                        ->skip(self::ACTIVITIES_PER_USER)
                        ->take($excessCount)
                        ->pluck('id');

                    if ($idsToDelete->isNotEmpty()) {
                        Activity::query()
                            ->whereIn('id', $idsToDelete)
                            ->delete();
                    }

                    return;
                }

                $missingCount = self::ACTIVITIES_PER_USER - $currentCount;

                if ($missingCount > 0) {
                    $companyIds = $user->companies()->pluck('id');
                    $contacts = $user
                        ->contacts()
                        ->select(['id', 'company_id'])
                        ->get();

                    Activity::factory()
                        ->count($missingCount)
                        ->for($user)
                        ->state(function () use (
                            $companyIds,
                            $contacts,
                        ): array {
                            if (
                                $contacts->isNotEmpty() &&
                                fake()->boolean(55)
                            ) {
                                $contact = $contacts->random();

                                return [
                                    'contact_id' => (int) $contact->id,
                                    'company_id' => $contact->company_id,
                                ];
                            }

                            if ($companyIds->isEmpty() || fake()->boolean(35)) {
                                return [
                                    'company_id' => null,
                                    'contact_id' => null,
                                ];
                            }

                            return [
                                'company_id' => (int) $companyIds->random(),
                                'contact_id' => null,
                            ];
                        })
                        ->create();
                }
            });
    }
}
