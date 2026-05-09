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
    private const COMPANIES_PER_USER = 8;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::query()
            ->select("id")
            ->withCount("companies")
            ->lazyById(250)
            ->each(function (User $user): void {
                $missingCount =
                    self::COMPANIES_PER_USER - (int) $user->companies_count;

                if ($missingCount <= 0) {
                    return;
                }

                Company::factory()->count($missingCount)->for($user)->create();
            });
    }
}
