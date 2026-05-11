<?php

namespace App\Concerns;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TaskValidationRules
{
    /**
     * Get the validation rules for storing/updating a task.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function taskRules(int $userId, ?int $taskId = null): array
    {
        return [
            'user_id' => ['prohibited'],
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists(Company::class, 'id')->where(
                    fn ($query) => $query->where('user_id', $userId),
                ),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists(Contact::class, 'id')->where(
                    fn ($query) => $query->where('user_id', $userId),
                ),
                function (string $attribute, mixed $value, \Closure $fail) use (
                    $userId,
                ): void {
                    $contactId = $this->nullableInteger($value);

                    if ($contactId === null) {
                        return;
                    }

                    $companyId = $this->nullableInteger(
                        request()->input('company_id'),
                    );

                    if ($companyId === null) {
                        return;
                    }

                    $contactCompanyId = Contact::query()
                        ->where('user_id', $userId)
                        ->whereKey($contactId)
                        ->value('company_id');

                    if ((int) ($contactCompanyId ?? 0) !== $companyId) {
                        $fail(
                            __(
                                'The selected contact does not belong to the selected company.',
                            ),
                        );
                    }
                },
            ],
            'activity_id' => [
                'nullable',
                'integer',
                Rule::exists(Activity::class, 'id')->where(
                    fn ($query) => $query->where('user_id', $userId),
                ),
                function (string $attribute, mixed $value, \Closure $fail) use (
                    $userId,
                ): void {
                    $activityId = $this->nullableInteger($value);

                    if ($activityId === null) {
                        return;
                    }

                    $activity = Activity::query()
                        ->select(['id', 'company_id', 'contact_id'])
                        ->where('user_id', $userId)
                        ->whereKey($activityId)
                        ->first();

                    if ($activity === null) {
                        return;
                    }

                    $companyId = $this->nullableInteger(
                        request()->input('company_id'),
                    );

                    if (
                        $companyId !== null &&
                        (int) ($activity->company_id ?? 0) !== $companyId
                    ) {
                        $fail(
                            __(
                                'The selected activity is not linked to the selected company.',
                            ),
                        );
                    }

                    $contactId = $this->nullableInteger(
                        request()->input('contact_id'),
                    );

                    if (
                        $contactId !== null &&
                        (int) ($activity->contact_id ?? 0) !== $contactId
                    ) {
                        $fail(
                            __(
                                'The selected activity is not linked to the selected contact.',
                            ),
                        );
                    }
                },
            ],
            'name' => $this->nameRules($userId, $taskId),
            'type' => ['required', 'string', Rule::in(Task::types())],
            'status' => ['required', 'string', Rule::in(Task::statuses())],
            'source' => ['nullable', 'string', 'max:120'],
            'task_at' => ['required', 'date'],
            'next_follow_up_at' => [
                'nullable',
                'date',
                'after_or_equal:task_at',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $status = strtolower((string) request()->input('status'));

                    if (in_array($status, ['completed', 'canceled'], true)) {
                        $fail(
                            __(
                                'Completed or canceled tasks cannot have a next follow-up date.',
                            ),
                        );
                    }
                },
            ],
            'is_active' => [
                'required',
                'boolean',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ): void {
                    $status = strtolower((string) request()->input('status'));
                    $isActive = filter_var(
                        $value,
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE,
                    );

                    if ($isActive !== true) {
                        return;
                    }

                    if (in_array($status, ['completed', 'canceled'], true)) {
                        $fail(
                            __('Completed or canceled tasks must be inactive.'),
                        );
                    }
                },
            ],
            'outcome' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules for the task name field.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(int $userId, ?int $taskId = null): array
    {
        $unique = Rule::unique(Task::class, 'name')->where(
            fn ($query) => $query->where('user_id', $userId),
        );

        if ($taskId !== null) {
            $unique = $unique->ignore($taskId);
        }

        return ['required', 'string', 'max:255', $unique];
    }

    /**
     * Sanitize incoming payload before validating.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitizeTaskInput(array $input): array
    {
        $stringFields = ['name', 'type', 'status', 'source', 'outcome'];

        foreach ($stringFields as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            if (! is_string($input[$field])) {
                continue;
            }

            $value = trim(strip_tags($input[$field]));
            $input[$field] = $value === '' ? null : $value;
        }

        if (array_key_exists('notes', $input) && is_string($input['notes'])) {
            $notes = trim(strip_tags($input['notes']));
            $input['notes'] =
                $notes === '' ? null : preg_replace('/\r\n|\r/', "\n", $notes);
        }

        if (is_string($input['status'] ?? null)) {
            $input['status'] = strtolower($input['status']);
        }

        if (is_string($input['type'] ?? null)) {
            $input['type'] = strtolower($input['type']);
        }

        foreach (
            ['company_id', 'contact_id', 'activity_id'] as $foreignKeyField
        ) {
            if (
                array_key_exists($foreignKeyField, $input) &&
                $input[$foreignKeyField] === ''
            ) {
                $input[$foreignKeyField] = null;
            }
        }

        foreach (['task_at', 'next_follow_up_at'] as $dateField) {
            if (
                array_key_exists($dateField, $input) &&
                $input[$dateField] === ''
            ) {
                $input[$dateField] = null;
            }
        }

        $userId = request()->user()?->id;

        if ($userId !== null) {
            $companyId = $this->nullableInteger($input['company_id'] ?? null);
            $contactId = $this->nullableInteger($input['contact_id'] ?? null);
            $activityId = $this->nullableInteger($input['activity_id'] ?? null);

            if ($activityId !== null) {
                $activity = Activity::query()
                    ->select(['id', 'company_id', 'contact_id'])
                    ->where('user_id', $userId)
                    ->whereKey($activityId)
                    ->first();

                if ($activity !== null) {
                    if ($companyId === null && $activity->company_id !== null) {
                        $input['company_id'] = (int) $activity->company_id;
                        $companyId = (int) $activity->company_id;
                    }

                    if ($contactId === null && $activity->contact_id !== null) {
                        $input['contact_id'] = (int) $activity->contact_id;
                        $contactId = (int) $activity->contact_id;
                    }
                }
            }

            if ($companyId === null && $contactId !== null) {
                $contactCompanyId = Contact::query()
                    ->where('user_id', $userId)
                    ->whereKey($contactId)
                    ->value('company_id');

                $input['company_id'] =
                    $contactCompanyId === null ? null : (int) $contactCompanyId;
            }
        }

        if (array_key_exists('is_active', $input)) {
            $bool = filter_var(
                $input['is_active'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($bool !== null) {
                $input['is_active'] = $bool;
            }
        }

        return $input;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }
}
