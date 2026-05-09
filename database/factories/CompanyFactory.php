<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            "user_id" => User::factory(),
            "name" => $name,
            "legal_name" => fake()->optional()->company() . " LLC",
            "status" => fake()->randomElement(Company::statuses()),
            "industry" => fake()
                ->optional()
                ->randomElement([
                    "Technology",
                    "Healthcare",
                    "Finance",
                    "Retail",
                    "Manufacturing",
                    "Education",
                    "Real Estate",
                ]),
            "source" => fake()
                ->optional()
                ->randomElement([
                    "Inbound",
                    "Outbound",
                    "Referral",
                    "Event",
                    "Partnership",
                ]),
            "ownership_type" => fake()
                ->optional()
                ->randomElement([
                    "Private",
                    "Public",
                    "Subsidiary",
                    "Non-profit",
                ]),
            "founded_year" => fake()
                ->optional()
                ->numberBetween(1950, now()->year),
            "employee_count" => fake()->optional()->numberBetween(5, 5000),
            "annual_revenue" => fake()
                ->optional()
                ->randomFloat(2, 50000, 50000000),
            "website" => fake()->optional()->url(),
            "linkedin_url" => fake()->optional()->url(),
            "email" => fake()->optional()->companyEmail(),
            "billing_email" => fake()->optional()->companyEmail(),
            "phone" => fake()->optional()->phoneNumber(),
            "support_phone" => fake()->optional()->phoneNumber(),
            "timezone" => fake()->optional()->timezone(),
            "preferred_contact_method" => fake()
                ->optional()
                ->randomElement(Company::preferredContactMethods()),
            "tax_id" => fake()->optional()->bothify("??-#######"),
            "primary_contact_name" => fake()->optional()->name(),
            "primary_contact_email" => fake()->optional()->safeEmail(),
            "primary_contact_phone" => fake()->optional()->phoneNumber(),
            "address_line_1" => fake()->optional()->streetAddress(),
            "address_line_2" => fake()->optional()->words(2, true),
            "city" => fake()->optional()->city(),
            "state" => fake()
                ->optional()
                ->randomElement(["CA", "NY", "TX", "FL", "WA"]),
            "postal_code" => fake()->optional()->postcode(),
            "country" => fake()->optional()->country(),
            "last_contacted_at" => fake()
                ->optional()
                ->dateTimeBetween("-120 days", "now"),
            "next_follow_up_at" => fake()
                ->optional()
                ->dateTimeBetween("now", "+60 days"),
            "is_active" => fake()->boolean(85),
            "notes" => fake()->optional()->realText(200),
        ];
    }
}
