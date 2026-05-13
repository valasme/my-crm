<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(Deal::statuses());
        $isClosed = in_array($status, Deal::closedStatuses(), true);

        $probability = match ($status) {
            'lead' => fake()->numberBetween(10, 30),
            'qualified' => fake()->numberBetween(35, 55),
            'proposal' => fake()->numberBetween(60, 75),
            'negotiation' => fake()->numberBetween(80, 95),
            'won' => 100,
            'lost' => 0,
            default => 10,
        };

        return [
            'user_id' => User::factory(),
            'company_id' => null,
            'contact_id' => null,
            'activity_id' => null,
            'name' => fake()->unique()->sentence(3),
            'type' => fake()->randomElement(Deal::types()),
            'status' => $status,
            'source' => fake()
                ->optional()
                ->randomElement([
                    'Inbound',
                    'Outbound',
                    'Referral',
                    'Event',
                    'Partnership',
                    'Website',
                ]),
            'amount' => fake()->randomFloat(2, 1000, 750000),
            'currency' => fake()->randomElement(Deal::currencies()),
            'probability' => $probability,
            'deal_at' => fake()->dateTimeBetween('-120 days', 'now'),
            'expected_close_at' => $isClosed
                ? null
                : fake()->optional()->dateTimeBetween('now', '+120 days'),
            'closed_at' => $isClosed
                ? fake()->dateTimeBetween('-45 days', 'now')
                : null,
            'next_follow_up_at' => $isClosed
                ? null
                : fake()->optional()->dateTimeBetween('now', '+45 days'),
            'is_active' => ! $isClosed,
            'sort_order' => fake()->numberBetween(0, 200),
            'outcome' => fake()->optional()->sentence(10),
            'notes' => fake()->optional()->realText(200),
        ];
    }

    /**
     * Associate this deal to one of the user's companies when available.
     */
    public function withCompany(): static
    {
        return $this->afterMaking(function (Deal $deal): void {
            if ($deal->company_id !== null || $deal->user_id === null) {
                return;
            }

            $deal->company_id = Company::query()
                ->where('user_id', $deal->user_id)
                ->inRandomOrder()
                ->value('id');
        })->afterCreating(function (Deal $deal): void {
            if ($deal->company_id !== null) {
                return;
            }

            $companyId = Company::query()
                ->where('user_id', $deal->user_id)
                ->inRandomOrder()
                ->value('id');

            if ($companyId === null) {
                return;
            }

            $deal
                ->forceFill([
                    'company_id' => $companyId,
                ])
                ->save();
        });
    }

    /**
     * Associate this deal to one of the user's contacts when available.
     */
    public function withContact(): static
    {
        return $this->afterMaking(function (Deal $deal): void {
            if ($deal->contact_id !== null || $deal->user_id === null) {
                return;
            }

            $contact = Contact::query()
                ->select(['id', 'company_id'])
                ->where('user_id', $deal->user_id)
                ->inRandomOrder()
                ->first();

            if ($contact === null) {
                return;
            }

            $deal->contact_id = $contact->id;

            if ($deal->company_id === null) {
                $deal->company_id = $contact->company_id;
            }
        })->afterCreating(function (Deal $deal): void {
            if ($deal->contact_id !== null) {
                return;
            }

            $contact = Contact::query()
                ->select(['id', 'company_id'])
                ->where('user_id', $deal->user_id)
                ->inRandomOrder()
                ->first();

            if ($contact === null) {
                return;
            }

            $deal
                ->forceFill([
                    'contact_id' => $contact->id,
                    'company_id' => $deal->company_id ?? $contact->company_id,
                ])
                ->save();
        });
    }

    /**
     * Associate this deal to one of the user's activities when available.
     */
    public function withActivity(): static
    {
        return $this->afterMaking(function (Deal $deal): void {
            if ($deal->activity_id !== null || $deal->user_id === null) {
                return;
            }

            $activity = Activity::query()
                ->select(['id', 'company_id', 'contact_id'])
                ->where('user_id', $deal->user_id)
                ->inRandomOrder()
                ->first();

            if ($activity === null) {
                return;
            }

            $deal->activity_id = $activity->id;
            $deal->company_id = $deal->company_id ?? $activity->company_id;
            $deal->contact_id = $deal->contact_id ?? $activity->contact_id;
        })->afterCreating(function (Deal $deal): void {
            if ($deal->activity_id !== null) {
                return;
            }

            $activity = Activity::query()
                ->select(['id', 'company_id', 'contact_id'])
                ->where('user_id', $deal->user_id)
                ->inRandomOrder()
                ->first();

            if ($activity === null) {
                return;
            }

            $deal
                ->forceFill([
                    'activity_id' => $activity->id,
                    'company_id' => $deal->company_id ?? $activity->company_id,
                    'contact_id' => $deal->contact_id ?? $activity->contact_id,
                ])
                ->save();
        });
    }
}
