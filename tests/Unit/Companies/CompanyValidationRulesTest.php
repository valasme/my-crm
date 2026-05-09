<?php

use App\Concerns\CompanyValidationRules;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function companyRulesHarness(): object
{
    return new class
    {
        use CompanyValidationRules;

        public function rules(int $userId, ?int $companyId = null): array
        {
            return $this->companyRules($userId, $companyId);
        }

        /**
         * @param  array<string, mixed>  $input
         * @return array<string, mixed>
         */
        public function sanitize(array $input): array
        {
            return $this->sanitizeCompanyInput($input);
        }
    };
}

test('company validation rules accept a complete valid payload', function () {
    $user = User::factory()->create();

    $validator = Validator::make(
        [
            'name' => 'Valid Co',
            'legal_name' => 'Valid Co LLC',
            'status' => 'lead',
            'industry' => 'Technology',
            'source' => 'Inbound',
            'ownership_type' => 'Private',
            'founded_year' => 2012,
            'employee_count' => 150,
            'annual_revenue' => 1500000.75,
            'website' => 'https://valid.example',
            'linkedin_url' => 'https://linkedin.com/company/valid',
            'email' => 'info@valid.example',
            'billing_email' => 'billing@valid.example',
            'phone' => '+1-555-0100',
            'support_phone' => '+1-555-0111',
            'timezone' => 'UTC',
            'preferred_contact_method' => 'email',
            'tax_id' => '12-3456789',
            'primary_contact_name' => 'Jordan Lee',
            'primary_contact_email' => 'jordan@valid.example',
            'primary_contact_phone' => '+1-555-0199',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 2',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'United States',
            'last_contacted_at' => '2026-01-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => true,
            'notes' => 'Important account.',
        ],
        companyRulesHarness()->rules($user->id),
    );

    expect($validator->passes())->toBeTrue();
});

test(
    'company validation rules enforce unique company names per user',
    function () {
        $user = User::factory()->create();

        $existing = Company::factory()
            ->for($user)
            ->create(['name' => 'Unique Name']);

        $failingValidator = Validator::make(
            [
                'name' => 'Unique Name',
                'status' => 'lead',
                'is_active' => true,
            ],
            companyRulesHarness()->rules($user->id),
        );

        expect($failingValidator->fails())
            ->toBeTrue()
            ->and($failingValidator->errors()->has('name'))
            ->toBeTrue();

        $passingUpdateValidator = Validator::make(
            [
                'name' => 'Unique Name',
                'status' => 'lead',
                'is_active' => true,
            ],
            companyRulesHarness()->rules($user->id, $existing->id),
        );

        expect($passingUpdateValidator->passes())->toBeTrue();
    },
);

test('company validation rules enforce allowed statuses', function () {
    $user = User::factory()->create();

    $validator = Validator::make(
        [
            'name' => 'Status Co',
            'status' => 'invalid',
            'is_active' => true,
        ],
        companyRulesHarness()->rules($user->id),
    );

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->has('status'))
        ->toBeTrue()
        ->and(Company::statuses())
        ->toBe(['lead', 'prospect', 'customer', 'churned']);
});

test(
    'company validation rules enforce linkedin host and supported contact methods',
    function () {
        $user = User::factory()->create();

        $validator = Validator::make(
            [
                'name' => 'LinkedIn Co',
                'status' => 'lead',
                'is_active' => true,
                'linkedin_url' => 'https://example.com/company/not-linkedin',
                'preferred_contact_method' => 'carrier-pigeon',
            ],
            companyRulesHarness()->rules($user->id),
        );

        expect($validator->fails())
            ->toBeTrue()
            ->and($validator->errors()->has('linkedin_url'))
            ->toBeTrue()
            ->and($validator->errors()->has('preferred_contact_method'))
            ->toBeTrue();
    },
);

test(
    'company validation rules reject deceptive linkedin-like domains',
    function () {
        $user = User::factory()->create();

        $validator = Validator::make(
            [
                'name' => 'Spoofed LinkedIn Co',
                'status' => 'lead',
                'is_active' => true,
                'linkedin_url' => 'https://evil-linkedin.com/company/not-linkedin',
            ],
            companyRulesHarness()->rules($user->id),
        );

        expect($validator->fails())
            ->toBeTrue()
            ->and($validator->errors()->has('linkedin_url'))
            ->toBeTrue();
    },
);

test(
    'company sanitization trims text, normalizes urls and lowercases emails',
    function () {
        $sanitized = companyRulesHarness()->sanitize([
            'name' => '  <b>Acme</b>  ',
            'email' => ' SALES@EXAMPLE.COM ',
            'billing_email' => ' BILLING@EXAMPLE.COM ',
            'website' => 'example.org',
            'linkedin_url' => 'linkedin.com/company/acme',
            'preferred_contact_method' => 'EMAIL',
            'notes' => '<script>alert(1)</script> Hello',
        ]);

        expect($sanitized['name'])
            ->toBe('Acme')
            ->and($sanitized['email'])
            ->toBe('sales@example.com')
            ->and($sanitized['billing_email'])
            ->toBe('billing@example.com')
            ->and($sanitized['website'])
            ->toBe('https://example.org')
            ->and($sanitized['linkedin_url'])
            ->toBe('https://linkedin.com/company/acme')
            ->and($sanitized['preferred_contact_method'])
            ->toBe('email')
            ->and($sanitized['notes'])
            ->toBe('alert(1) Hello');
    },
);
