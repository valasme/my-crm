<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(Task::statuses());

        return [
            'user_id' => User::factory(),
            'company_id' => null,
            'contact_id' => null,
            'activity_id' => null,
            'name' => fake()->unique()->sentence(3),
            'type' => fake()->randomElement(Task::types()),
            'status' => $status,
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
            'task_at' => fake()->dateTimeBetween('-45 days', '+60 days'),
            'next_follow_up_at' => $status === 'planned'
                    ? fake()->optional()->dateTimeBetween('now', '+90 days')
                    : null,
            'is_active' => $status === 'planned' ? fake()->boolean(85) : false,
            'outcome' => fake()->optional()->sentence(10),
            'notes' => fake()->optional()->realText(200),
        ];
    }

    /**
     * Associate this task to one of the user's companies when available.
     */
    public function withCompany(): static
    {
        return $this->afterMaking(function (Task $task): void {
            if ($task->company_id !== null || $task->user_id === null) {
                return;
            }

            $task->company_id = Company::query()
                ->where('user_id', $task->user_id)
                ->inRandomOrder()
                ->value('id');
        })->afterCreating(function (Task $task): void {
            if ($task->company_id !== null) {
                return;
            }

            $companyId = Company::query()
                ->where('user_id', $task->user_id)
                ->inRandomOrder()
                ->value('id');

            if ($companyId === null) {
                return;
            }

            $task
                ->forceFill([
                    'company_id' => $companyId,
                ])
                ->save();
        });
    }

    /**
     * Associate this task to one of the user's contacts when available.
     */
    public function withContact(): static
    {
        return $this->afterMaking(function (Task $task): void {
            if ($task->contact_id !== null || $task->user_id === null) {
                return;
            }

            $contact = Contact::query()
                ->select(['id', 'company_id'])
                ->where('user_id', $task->user_id)
                ->inRandomOrder()
                ->first();

            if ($contact === null) {
                return;
            }

            $task->contact_id = $contact->id;

            if ($task->company_id === null) {
                $task->company_id = $contact->company_id;
            }
        })->afterCreating(function (Task $task): void {
            if ($task->contact_id !== null) {
                return;
            }

            $contact = Contact::query()
                ->select(['id', 'company_id'])
                ->where('user_id', $task->user_id)
                ->inRandomOrder()
                ->first();

            if ($contact === null) {
                return;
            }

            $task
                ->forceFill([
                    'contact_id' => $contact->id,
                    'company_id' => $task->company_id ?? $contact->company_id,
                ])
                ->save();
        });
    }

    /**
     * Associate this task to one of the user's activities when available.
     */
    public function withActivity(): static
    {
        return $this->afterMaking(function (Task $task): void {
            if ($task->activity_id !== null || $task->user_id === null) {
                return;
            }

            $activity = Activity::query()
                ->select(['id', 'company_id', 'contact_id'])
                ->where('user_id', $task->user_id)
                ->inRandomOrder()
                ->first();

            if ($activity === null) {
                return;
            }

            $task->activity_id = $activity->id;
            $task->company_id = $task->company_id ?? $activity->company_id;
            $task->contact_id = $task->contact_id ?? $activity->contact_id;
        })->afterCreating(function (Task $task): void {
            if ($task->activity_id !== null) {
                return;
            }

            $activity = Activity::query()
                ->select(['id', 'company_id', 'contact_id'])
                ->where('user_id', $task->user_id)
                ->inRandomOrder()
                ->first();

            if ($activity === null) {
                return;
            }

            $task
                ->forceFill([
                    'activity_id' => $activity->id,
                    'company_id' => $task->company_id ?? $activity->company_id,
                    'contact_id' => $task->contact_id ?? $activity->contact_id,
                ])
                ->save();
        });
    }
}
