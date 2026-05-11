<?php

use App\Concerns\ActivityValidationRules;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function activityRulesHarness(): object
{
    return new class
    {
        use ActivityValidationRules;

        public function rules(int $userId, ?int $activityId = null): array
        {
            return $this->activityRules($userId, $activityId);
        }

        /**
         * @param  array<string, mixed>  $input
         * @return array<string, mixed>
         */
        public function sanitize(array $input): array
        {
            return $this->sanitizeActivityInput($input);
        }
    };
}

test('activity validation rules accept a complete valid payload', function () {
    $user = User::factory()->create();

    $company = Company::factory()->for($user)->create();
    $contact = Contact::factory()
        ->for($user)
        ->create(['company_id' => $company->id]);

    $validator = Validator::make(
        [
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'name' => 'Q1 Discovery Call',
            'type' => 'call',
            'status' => 'planned',
            'source' => 'Inbound',
            'activity_at' => '2026-01-10',
            'notes' => 'Important activity.',
        ],
        activityRulesHarness()->rules($user->id),
    );

    expect($validator->passes())->toBeTrue();
});

test(
    'activity validation rules enforce unique activity names per user',
    function () {
        $user = User::factory()->create();

        $existing = Activity::factory()
            ->for($user)
            ->create(['name' => 'Unique Name']);

        $failingValidator = Validator::make(
            [
                'name' => 'Unique Name',
                'type' => 'call',
                'status' => 'planned',
                'activity_at' => '2026-01-10',
            ],
            activityRulesHarness()->rules($user->id),
        );

        expect($failingValidator->fails())
            ->toBeTrue()
            ->and($failingValidator->errors()->has('name'))
            ->toBeTrue();

        $passingUpdateValidator = Validator::make(
            [
                'name' => 'Unique Name',
                'type' => 'call',
                'status' => 'planned',
                'activity_at' => '2026-01-10',
            ],
            activityRulesHarness()->rules($user->id, $existing->id),
        );

        expect($passingUpdateValidator->passes())->toBeTrue();
    },
);

test('activity validation rules enforce allowed statuses', function () {
    $user = User::factory()->create();

    $validator = Validator::make(
        [
            'name' => 'Status Activity',
            'type' => 'call',
            'status' => 'invalid',
            'activity_at' => '2026-01-10',
        ],
        activityRulesHarness()->rules($user->id),
    );

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->has('status'))
        ->toBeTrue()
        ->and(Activity::statuses())
        ->toBe(['planned', 'completed', 'canceled']);
});

test('activity validation rules enforce allowed activity types', function () {
    $user = User::factory()->create();

    $validator = Validator::make(
        [
            'name' => 'Type Activity',
            'type' => 'carrier-pigeon',
            'status' => 'planned',
            'activity_at' => '2026-01-10',
        ],
        activityRulesHarness()->rules($user->id),
    );

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->has('type'))
        ->toBeTrue()
        ->and(Activity::types())
        ->toBe(['call', 'email', 'meeting', 'note']);
});

test(
    'activity validation rules enforce company and contact ownership',
    function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUsersCompany = Company::factory()->for($otherUser)->create();
        $otherUsersContact = Contact::factory()
            ->for($otherUser)
            ->create(['company_id' => $otherUsersCompany->id]);

        $validator = Validator::make(
            [
                'name' => 'Ownership Activity',
                'type' => 'call',
                'status' => 'planned',
                'activity_at' => '2026-01-10',
                'company_id' => $otherUsersCompany->id,
                'contact_id' => $otherUsersContact->id,
            ],
            activityRulesHarness()->rules($user->id),
        );

        expect($validator->fails())
            ->toBeTrue()
            ->and($validator->errors()->has('company_id'))
            ->toBeTrue()
            ->and($validator->errors()->has('contact_id'))
            ->toBeTrue();
    },
);

test(
    'activity validation rules enforce contact and company consistency for same user records',
    function () {
        $user = User::factory()->create();

        $companyA = Company::factory()->for($user)->create();
        $companyB = Company::factory()->for($user)->create();

        $contactB = Contact::factory()
            ->for($user)
            ->create(['company_id' => $companyB->id]);

        $payload = [
            'name' => 'Relationship Activity',
            'type' => 'call',
            'status' => 'planned',
            'activity_at' => '2026-01-10',
            'company_id' => $companyA->id,
            'contact_id' => $contactB->id,
        ];

        request()->replace($payload);

        $validator = Validator::make(
            $payload,
            activityRulesHarness()->rules($user->id),
        );

        expect($validator->fails())
            ->toBeTrue()
            ->and($validator->errors()->has('contact_id'))
            ->toBeTrue();
    },
);

test(
    'activity sanitization trims text, lowercases enums, and normalizes nullable fields',
    function () {
        $sanitized = activityRulesHarness()->sanitize([
            'name' => '  <b>Alex</b>  ',
            'type' => 'EMAIL',
            'status' => 'COMPLETED',
            'source' => '  <i>Inbound</i>  ',
            'notes' => "<script>bad()</script> Hello\r\nWorld",
            'company_id' => '',
            'contact_id' => '',
        ]);

        expect($sanitized['name'])
            ->toBe('Alex')
            ->and($sanitized['type'])
            ->toBe('email')
            ->and($sanitized['status'])
            ->toBe('completed')
            ->and($sanitized['source'])
            ->toBe('Inbound')
            ->and($sanitized['notes'])
            ->toBe("bad() Hello\nWorld")
            ->and($sanitized['company_id'])
            ->toBeNull()
            ->and($sanitized['contact_id'])
            ->toBeNull();
    },
);
