<?php

namespace Database\Seeders;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Seeder;

class DealSeeder extends Seeder
{
    /**
     * @var int
     */
    private const DEALS_PER_USER = 40;

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
            ->withCount('deals')
            ->lazyById(250)
            ->each(function (User $user): void {
                $currentCount = (int) $user->deals_count;

                if ($currentCount > self::DEALS_PER_USER) {
                    $excessCount = $currentCount - self::DEALS_PER_USER;

                    $idsToDelete = Deal::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('id')
                        ->skip(self::DEALS_PER_USER)
                        ->take($excessCount)
                        ->pluck('id');

                    if ($idsToDelete->isNotEmpty()) {
                        Deal::query()->whereIn('id', $idsToDelete)->delete();
                    }

                    return;
                }

                $missingCount = self::DEALS_PER_USER - $currentCount;

                if ($missingCount > 0) {
                    $companyIds = $user->companies()->pluck('id');
                    $contacts = $user
                        ->contacts()
                        ->select(['id', 'company_id'])
                        ->get();
                    $activities = $user
                        ->activities()
                        ->select(['id', 'company_id', 'contact_id'])
                        ->get();

                    Deal::factory()
                        ->count($missingCount)
                        ->for($user)
                        ->state(function () use (
                            $companyIds,
                            $contacts,
                            $activities,
                        ): array {
                            if (
                                $activities->isNotEmpty() &&
                                fake()->boolean(35)
                            ) {
                                $activity = $activities->random();

                                return [
                                    'activity_id' => (int) $activity->id,
                                    'company_id' => $activity->company_id,
                                    'contact_id' => $activity->contact_id,
                                ];
                            }

                            if (
                                $contacts->isNotEmpty() &&
                                fake()->boolean(50)
                            ) {
                                $contact = $contacts->random();

                                return [
                                    'contact_id' => (int) $contact->id,
                                    'company_id' => $contact->company_id,
                                    'activity_id' => null,
                                ];
                            }

                            if ($companyIds->isEmpty() || fake()->boolean(35)) {
                                return [
                                    'company_id' => null,
                                    'contact_id' => null,
                                    'activity_id' => null,
                                ];
                            }

                            return [
                                'company_id' => (int) $companyIds->random(),
                                'contact_id' => null,
                                'activity_id' => null,
                            ];
                        })
                        ->create();
                }
            });
    }
}
