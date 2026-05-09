<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * @var int
     */
    private const COMPANIES_PER_USER = 50;

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
            ->withCount('companies')
            ->lazyById(250)
            ->each(function (User $user): void {
                $currentCount = (int) $user->companies_count;

                if ($currentCount > self::COMPANIES_PER_USER) {
                    $excessCount = $currentCount - self::COMPANIES_PER_USER;

                    $idsToDelete = Company::query()
                        ->where('user_id', $user->id)
                        ->orderByDesc('id')
                        ->skip(self::COMPANIES_PER_USER)
                        ->take($excessCount)
                        ->pluck('id');

                    if ($idsToDelete->isNotEmpty()) {
                        Company::query()->whereIn('id', $idsToDelete)->delete();
                    }

                    return;
                }

                $missingCount = self::COMPANIES_PER_USER - $currentCount;

                if ($missingCount > 0) {
                    Company::factory()
                        ->count($missingCount)
                        ->for($user)
                        ->create();
                }
            });
    }
}
