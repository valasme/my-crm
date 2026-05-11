<?php

namespace App\Concerns;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CompanyValidationRules
{
    /**
     * @var string
     */
    protected const PHONE_REGEX = '/^[0-9+()\-.\s]{7,30}$/';

    /**
     * Get the validation rules for storing/updating a company.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function companyRules(int $userId, ?int $companyId = null): array
    {
        $primaryContactRules = ['nullable', 'prohibited'];

        if ($companyId !== null) {
            $primaryContactRules = [
                'nullable',
                'integer',
                Rule::exists(Contact::class, 'id')->where(
                    fn ($query) => $query->where('user_id', $userId),
                ),
                function (string $attribute, mixed $value, \Closure $fail) use (
                    $userId,
                    $companyId,
                ): void {
                    $contactId = $this->nullableInteger($value);

                    if ($contactId === null) {
                        return;
                    }

                    $contactCompanyId = Contact::query()
                        ->where('user_id', $userId)
                        ->whereKey($contactId)
                        ->value('company_id');

                    if ((int) ($contactCompanyId ?? 0) !== $companyId) {
                        $fail(
                            __(
                                'The selected primary contact must belong to this company.',
                            ),
                        );
                    }
                },
            ];
        }

        return [
            'user_id' => ['prohibited'],
            'name' => $this->nameRules($userId, $companyId),
            'legal_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Company::statuses())],
            'industry' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'ownership_type' => ['nullable', 'string', 'max:120'],
            'founded_year' => [
                'nullable',
                'integer',
                'min:1600',
                'max:'.now()->year,
            ],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'annual_revenue' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
            'website' => [
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
                        (string) parse_url($value, PHP_URL_HOST),
                    );

                    if ($host === '' || ! str_contains($host, '.')) {
                        $fail(
                            __('The :attribute must be a valid website URL.', [
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
            'email' => ['nullable', 'email', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:'.self::PHONE_REGEX,
            ],
            'support_phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:'.self::PHONE_REGEX,
            ],
            'timezone' => ['nullable', 'timezone:all'],
            'preferred_contact_method' => [
                'nullable',
                'string',
                Rule::in(Company::preferredContactMethods()),
            ],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'primary_contact_id' => $primaryContactRules,
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
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
     * Get the validation rules for the company name field.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(int $userId, ?int $companyId = null): array
    {
        $unique = Rule::unique(Company::class, 'name')->where(
            fn ($query) => $query->where('user_id', $userId),
        );

        if ($companyId !== null) {
            $unique = $unique->ignore($companyId);
        }

        return ['required', 'string', 'max:255', $unique];
    }

    /**
     * Sanitize incoming payload before validating.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitizeCompanyInput(array $input): array
    {
        $stringFields = [
            'name',
            'legal_name',
            'status',
            'industry',
            'source',
            'ownership_type',
            'website',
            'linkedin_url',
            'email',
            'billing_email',
            'phone',
            'support_phone',
            'timezone',
            'preferred_contact_method',
            'tax_id',
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

        foreach (['email', 'billing_email'] as $emailField) {
            if (
                ! array_key_exists($emailField, $input) ||
                ! is_string($input[$emailField])
            ) {
                continue;
            }

            $email = strtolower(trim($input[$emailField]));
            $input[$emailField] = $email === '' ? null : $email;
        }

        foreach (['website', 'linkedin_url'] as $urlField) {
            if (! array_key_exists($urlField, $input)) {
                continue;
            }

            $input[$urlField] = $this->normalizeUrl($input[$urlField]);
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
            array_key_exists('founded_year', $input) &&
            $input['founded_year'] === ''
        ) {
            $input['founded_year'] = null;
        }

        if (
            array_key_exists('primary_contact_id', $input) &&
            $input['primary_contact_id'] === ''
        ) {
            $input['primary_contact_id'] = null;
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
