<?php

namespace App\Concerns;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ActivityValidationRules
{
    /**
     * Get the validation rules for storing/updating an activity.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function activityRules(
        int $userId,
        ?int $activityId = null,
    ): array {
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
            'name' => $this->nameRules($userId, $activityId),
            'type' => ['required', 'string', Rule::in(Activity::types())],
            'status' => ['required', 'string', Rule::in(Activity::statuses())],
            'source' => ['nullable', 'string', 'max:120'],
            'activity_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules for the activity name field.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(int $userId, ?int $activityId = null): array
    {
        $unique = Rule::unique(Activity::class, 'name')->where(
            fn ($query) => $query->where('user_id', $userId),
        );

        if ($activityId !== null) {
            $unique = $unique->ignore($activityId);
        }

        return ['required', 'string', 'max:255', $unique];
    }

    /**
     * Sanitize incoming payload before validating.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitizeActivityInput(array $input): array
    {
        $stringFields = ['name', 'type', 'status', 'source'];

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

        foreach (['company_id', 'contact_id'] as $foreignKeyField) {
            if (
                array_key_exists($foreignKeyField, $input) &&
                $input[$foreignKeyField] === ''
            ) {
                $input[$foreignKeyField] = null;
            }
        }

        if (
            array_key_exists('activity_at', $input) &&
            $input['activity_at'] === ''
        ) {
            $input['activity_at'] = null;
        }

        $userId = request()->user()?->id;

        if ($userId !== null) {
            $companyId = $this->nullableInteger($input['company_id'] ?? null);
            $contactId = $this->nullableInteger($input['contact_id'] ?? null);

            if ($companyId === null && $contactId !== null) {
                $contactCompanyId = Contact::query()
                    ->where('user_id', $userId)
                    ->whereKey($contactId)
                    ->value('company_id');

                $input['company_id'] =
                    $contactCompanyId === null ? null : (int) $contactCompanyId;
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
