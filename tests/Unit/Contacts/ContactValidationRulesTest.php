<?php

use App\Concerns\ContactValidationRules;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function contactRulesHarness(): object
{
    return new class
    {
        use ContactValidationRules;

        public function rules(int $userId, ?int $contactId = null): array
        {
            return $this->contactRules($userId, $contactId);
        }

        /**
         * @param  array<string, mixed>  $input
         * @return array<string, mixed>
         */
        public function sanitize(array $input): array
        {
            return $this->sanitizeContactInput($input);
        }
    };
}

test('contact validation rules accept a complete valid payload', function () {
    $user = User::factory()->create();
    $company = Company::factory()->for($user)->create();

    $validator = Validator::make(
        [
            'company_id' => $company->id,
            'name' => 'Jordan Lee',
            'job_title' => 'VP Sales',
            'status' => 'lead',
            'department' => 'Sales',
            'source' => 'Inbound',
            'email' => 'info@valid.example',
            'alternate_email' => 'alt@valid.example',
            'phone' => '+1-555-0100',
            'mobile_phone' => '+1-555-0111',
            'linkedin_url' => 'https://linkedin.com/in/jordan-lee',
            'timezone' => 'UTC',
            'preferred_contact_method' => 'email',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Suite 2',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'United States',
            'birthday' => '1990-04-18',
            'last_contacted_at' => '2026-01-10',
            'next_follow_up_at' => '2026-01-20',
            'is_active' => true,
            'notes' => 'Important contact.',
        ],
        contactRulesHarness()->rules($user->id),
    );

    expect($validator->passes())->toBeTrue();
});

test(
    'contact validation rules enforce unique contact names per user',
    function () {
        $user = User::factory()->create();

        $existing = Contact::factory()
            ->for($user)
            ->create(['name' => 'Unique Name']);

        $failingValidator = Validator::make(
            [
                'name' => 'Unique Name',
                'status' => 'lead',
                'is_active' => true,
            ],
            contactRulesHarness()->rules($user->id),
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
            contactRulesHarness()->rules($user->id, $existing->id),
        );

        expect($passingUpdateValidator->passes())->toBeTrue();
    },
);

test('contact validation rules enforce allowed statuses', function () {
    $user = User::factory()->create();

    $validator = Validator::make(
        [
            'name' => 'Status Person',
            'status' => 'invalid',
            'is_active' => true,
        ],
        contactRulesHarness()->rules($user->id),
    );

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->has('status'))
        ->toBeTrue()
        ->and(Contact::statuses())
        ->toBe(['lead', 'prospect', 'customer', 'churned']);
});

test(
    'contact validation rules enforce company ownership and supported contact methods',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherUsersCompany = Company::factory()->for($otherUser)->create();

        $validator = Validator::make(
            [
                'name' => 'Ownership Person',
                'status' => 'lead',
                'is_active' => true,
                'company_id' => $otherUsersCompany->id,
                'preferred_contact_method' => 'carrier-pigeon',
            ],
            contactRulesHarness()->rules($user->id),
        );

        expect($validator->fails())
            ->toBeTrue()
            ->and($validator->errors()->has('company_id'))
            ->toBeTrue()
            ->and($validator->errors()->has('preferred_contact_method'))
            ->toBeTrue();
    },
);

test(
    'contact validation rules reject deceptive linkedin-like domains',
    function () {
        $user = User::factory()->create();

        $validator = Validator::make(
            [
                'name' => 'Spoofed LinkedIn Person',
                'status' => 'lead',
                'is_active' => true,
                'linkedin_url' => 'https://evil-linkedin.com/in/not-linkedin',
            ],
            contactRulesHarness()->rules($user->id),
        );

        expect($validator->fails())
            ->toBeTrue()
            ->and($validator->errors()->has('linkedin_url'))
            ->toBeTrue();
    },
);

test(
    'contact sanitization trims text, normalizes urls and lowercases emails',
    function () {
        $sanitized = contactRulesHarness()->sanitize([
            'name' => '  <b>Alex</b>  ',
            'email' => ' SALES@EXAMPLE.COM ',
            'alternate_email' => ' ALT@EXAMPLE.COM ',
            'linkedin_url' => 'linkedin.com/in/alex',
            'preferred_contact_method' => 'EMAIL',
            'notes' => '<script>alert(1)</script> Hello',
            'company_id' => '',
        ]);

        expect($sanitized['name'])
            ->toBe('Alex')
            ->and($sanitized['email'])
            ->toBe('sales@example.com')
            ->and($sanitized['alternate_email'])
            ->toBe('alt@example.com')
            ->and($sanitized['linkedin_url'])
            ->toBe('https://linkedin.com/in/alex')
            ->and($sanitized['preferred_contact_method'])
            ->toBe('email')
            ->and($sanitized['company_id'])
            ->toBeNull()
            ->and($sanitized['notes'])
            ->toBe('alert(1) Hello');
    },
);
