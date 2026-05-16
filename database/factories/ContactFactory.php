<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => null,
            'name' => fake()->unique()->name(),
            'job_title' => fake()->optional()->jobTitle(),
            'status' => fake()->randomElement(Contact::statuses()),
            'department' => fake()
                ->optional()
                ->randomElement([
                    'Sales',
                    'Marketing',
                    'Finance',
                    'Operations',
                    'Customer Success',
                    'Engineering',
                ]),
            'source' => fake()
                ->optional()
                ->randomElement([
                    'Inbound',
                    'Outbound',
                    'Referral',
                    'Event',
                    'Partnership',
                ]),
            'email' => fake()->optional()->safeEmail(),
            'alternate_email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'mobile_phone' => fake()->optional()->phoneNumber(),
            'linkedin_url' => fake()->boolean(60)
                ? 'https://linkedin.com/in/'.fake()->slug(2)
                : null,
            'timezone' => fake()->optional()->timezone(),
            'preferred_contact_method' => fake()
                ->optional()
                ->randomElement(Contact::preferredContactMethods()),
            'address_line_1' => fake()->optional()->streetAddress(),
            'address_line_2' => fake()->optional()->words(2, true),
            'city' => fake()->optional()->city(),
            'state' => fake()
                ->optional()
                ->randomElement(['CA', 'NY', 'TX', 'FL', 'WA']),
            'postal_code' => fake()->optional()->postcode(),
            'country' => fake()->optional()->country(),
            'birthday' => fake()
                ->optional()
                ->dateTimeBetween('-70 years', '-18 years'),
            'last_contacted_at' => fake()
                ->optional()
                ->dateTimeBetween('-120 days', 'now'),
            'next_follow_up_at' => fake()
                ->optional()
                ->dateTimeBetween('now', '+60 days'),
            'is_active' => fake()->boolean(85),
            'notes' => fake()->optional()->realText(200),
        ];
    }

    /**
     * Associate this contact to one of the user's companies when available.
     */
    public function withCompany(): static
    {
        return $this->afterMaking(function (Contact $contact): void {
            if ($contact->company_id !== null || $contact->user_id === null) {
                return;
            }

            $contact->company_id = Company::query()
                ->where('user_id', $contact->user_id)
                ->inRandomOrder()
                ->value('id');
        })->afterCreating(function (Contact $contact): void {
            if ($contact->company_id !== null) {
                return;
            }

            $companyId = Company::query()
                ->where('user_id', $contact->user_id)
                ->inRandomOrder()
                ->value('id');

            if ($companyId === null) {
                return;
            }

            $contact
                ->forceFill([
                    'company_id' => $companyId,
                ])
                ->save();
        });
    }
}
