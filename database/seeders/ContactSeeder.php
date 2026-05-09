<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * @var int
     */
    private const CONTACTS_PER_USER = 50;

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
            ->withCount('contacts')
            ->lazyById(250)
            ->each(function (User $user): void {
                $currentCount = (int) $user->contacts_count;

                if ($currentCount > self::CONTACTS_PER_USER) {
                    $excessCount = $currentCount - self::CONTACTS_PER_USER;

                    $idsToDelete = Contact::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('id')
                        ->skip(self::CONTACTS_PER_USER)
                        ->take($excessCount)
                        ->pluck('id');

                    if ($idsToDelete->isNotEmpty()) {
                        Contact::query()->whereIn('id', $idsToDelete)->delete();
                    }

                    return;
                }

                $missingCount = self::CONTACTS_PER_USER - $currentCount;

                if ($missingCount > 0) {
                    $companyIds = $user->companies()->pluck('id');

                    Contact::factory()
                        ->count($missingCount)
                        ->for($user)
                        ->state(function () use ($companyIds): array {
                            if ($companyIds->isEmpty() || fake()->boolean(35)) {
                                return ['company_id' => null];
                            }

                            return [
                                'company_id' => (int) $companyIds->random(),
                            ];
                        })
                        ->create();
                }
            });
    }
}
