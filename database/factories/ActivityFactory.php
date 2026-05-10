<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'contact_id' => null,
            'name' => fake()->unique()->sentence(3),
            'type' => fake()->randomElement(Activity::types()),
            'status' => fake()->randomElement(Activity::statuses()),
            'source' => fake()
                ->optional()
                ->randomElement([
                    'Inbound',
                    'Outbound',
                    'Referral',
                    'Event',
                    'Partnership',
                    'Support',
                ]),
            'activity_at' => fake()->dateTimeBetween('-120 days', 'now'),
            'notes' => fake()->optional()->realText(200),
        ];
    }

    /**
     * Associate this activity to one of the user's companies when available.
     */
    public function withCompany(): static
    {
        return $this->afterMaking(function (Activity $activity): void {
            if ($activity->company_id !== null || $activity->user_id === null) {
                return;
            }

            $activity->company_id = Company::query()
                ->where('user_id', $activity->user_id)
                ->inRandomOrder()
                ->value('id');
        })->afterCreating(function (Activity $activity): void {
            if ($activity->company_id !== null) {
                return;
            }

            $companyId = Company::query()
                ->where('user_id', $activity->user_id)
                ->inRandomOrder()
                ->value('id');

            if ($companyId === null) {
                return;
            }

            $activity
                ->forceFill([
                    'company_id' => $companyId,
                ])
                ->save();
        });
    }

    /**
     * Associate this activity to one of the user's contacts when available.
     */
    public function withContact(): static
    {
        return $this->afterMaking(function (Activity $activity): void {
            if ($activity->contact_id !== null || $activity->user_id === null) {
                return;
            }

            $contact = Contact::query()
                ->select(['id', 'company_id'])
                ->where('user_id', $activity->user_id)
                ->inRandomOrder()
                ->first();

            if ($contact === null) {
                return;
            }

            $activity->contact_id = $contact->id;

            if ($activity->company_id === null) {
                $activity->company_id = $contact->company_id;
            }
        })->afterCreating(function (Activity $activity): void {
            if ($activity->contact_id !== null) {
                return;
            }

            $contact = Contact::query()
                ->select(['id', 'company_id'])
                ->where('user_id', $activity->user_id)
                ->inRandomOrder()
                ->first();

            if ($contact === null) {
                return;
            }

            $activity
                ->forceFill([
                    'contact_id' => $contact->id,
                    'company_id' => $activity->company_id ?? $contact->company_id,
                ])
                ->save();
        });
    }
}
