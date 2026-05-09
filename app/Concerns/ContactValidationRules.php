<?php

namespace App\Concerns;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ContactValidationRules
{
    /**
     * @var string
     */
    protected const PHONE_REGEX = '/^[0-9+()\-.\s]{7,30}$/';

    /**
     * Get the validation rules for storing/updating a contact.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function contactRules(int $userId, ?int $contactId = null): array
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
            'name' => $this->nameRules($userId, $contactId),
            'job_title' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Contact::statuses())],
            'department' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'alternate_email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:'.self::PHONE_REGEX,
            ],
            'mobile_phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:'.self::PHONE_REGEX,
            ],
            'linkedin_url' => [
                'nullable',
                'url:http,https',
                'max:2048',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail,
                ): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $host = strtolower(
                        trim((string) parse_url($value, PHP_URL_HOST), '.'),
                    );

                    if (
                        $host !== '' &&
                        $host !== 'linkedin.com' &&
                        ! str_ends_with($host, '.linkedin.com')
                    ) {
                        $fail(
                            __('The :attribute must be a valid LinkedIn URL.', [
                                'attribute' => str_replace(
                                    '_',
                                    ' ',
                                    $attribute,
                                ),
                            ]),
                        );
                    }
                },
            ],
            'timezone' => ['nullable', 'timezone:all'],
            'preferred_contact_method' => [
                'nullable',
                'string',
                Rule::in(Contact::preferredContactMethods()),
            ],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'last_contacted_at' => ['nullable', 'date'],
            'next_follow_up_at' => [
                'nullable',
                'date',
                'after_or_equal:last_contacted_at',
            ],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules for the contact name field.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(int $userId, ?int $contactId = null): array
    {
        $unique = Rule::unique(Contact::class, 'name')->where(
            fn ($query) => $query->where('user_id', $userId),
        );

        if ($contactId !== null) {
            $unique = $unique->ignore($contactId);
        }

        return ['required', 'string', 'max:255', $unique];
    }

    /**
     * Sanitize incoming payload before validating.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitizeContactInput(array $input): array
    {
        $stringFields = [
            'name',
            'job_title',
            'status',
            'department',
            'source',
            'email',
            'alternate_email',
            'phone',
            'mobile_phone',
            'linkedin_url',
            'timezone',
            'preferred_contact_method',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
        ];

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

        foreach (['email', 'alternate_email'] as $emailField) {
            if (
                ! array_key_exists($emailField, $input) ||
                ! is_string($input[$emailField])
            ) {
                continue;
            }

            $email = strtolower(trim($input[$emailField]));
            $input[$emailField] = $email === '' ? null : $email;
        }

        if (array_key_exists('linkedin_url', $input)) {
            $input['linkedin_url'] = $this->normalizeUrl(
                $input['linkedin_url'],
            );
        }

        if (is_string($input['status'] ?? null)) {
            $input['status'] = strtolower($input['status']);
        }

        if (is_string($input['preferred_contact_method'] ?? null)) {
            $input['preferred_contact_method'] = strtolower(
                $input['preferred_contact_method'],
            );
        }

        if (
            array_key_exists('company_id', $input) &&
            $input['company_id'] === ''
        ) {
            $input['company_id'] = null;
        }

        foreach (
            ['birthday', 'last_contacted_at', 'next_follow_up_at'] as $dateField
        ) {
            if (
                array_key_exists($dateField, $input) &&
                $input[$dateField] === ''
            ) {
                $input[$dateField] = null;
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

    protected function normalizeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '') {
            return null;
        }

        if (
            ! str_starts_with(strtolower($url), 'http://') &&
            ! str_starts_with(strtolower($url), 'https://')
        ) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
