<?php

use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Create Company")] class extends Component {
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize("create", Company::class);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return Company::statuses();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function preferredContactMethods(): array
    {
        return Company::preferredContactMethods();
    }
};
?>

<div class="mx-auto flex h-full w-full max-w-[120rem] flex-1 flex-col gap-6 rounded-xl">
    @php
        $indexQuery = array_filter(
            request()->only([
                'search',
                'status',
                'active',
                'follow_up',
                'sort',
                'direction',
                'per_page',
                'page',
            ]),
            fn ($value): bool => $value !== '' && $value !== null,
        );
    @endphp

    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="xl" as="h1" id="create-company-heading" aria-label="{{ __('Create Company') }}">
                    {{ __('Create Company') }}
                </flux:heading>
                <flux:subheading>{{ __('Add a new company account to your CRM.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" :href="route('companies.index', $indexQuery)" wire:navigate>
                {{ __('Companies') }}
            </flux:button>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('companies.index', $indexQuery)" wire:navigate>
                <flux:icon.arrow-left variant="micro" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    @if ($errors->has('company'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('company') }}</flux:badge>
        </div>
    @endif

    <form method="POST" action="{{ route('companies.store') }}" class="space-y-6" novalidate>
        @csrf

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Company Profile') }}</flux:heading>
                <flux:subheading>{{ __('Core details and account lifecycle information.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input name="name" :label="__('Company name')" :value="old('name')" required autofocus />
                <flux:input name="legal_name" :label="__('Legal name (optional)')" :value="old('legal_name')" />

                <div class="md:col-span-2">
                    <flux:radio.group name="status" :label="__('Status')" variant="segmented" required>
                        @foreach ($this->statuses as $status)
                            <flux:radio value="{{ $status }}" :checked="old('status', 'lead') === $status">
                                {{ \Illuminate\Support\Str::headline($status) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2">
                    <flux:radio.group name="is_active" :label="__('Account state')" variant="segmented" required>
                        <flux:radio value="1" :checked="(string) old('is_active', '1') === '1'">{{ __('Active') }}</flux:radio>
                        <flux:radio value="0" :checked="(string) old('is_active') === '0'">{{ __('Inactive') }}</flux:radio>
                    </flux:radio.group>
                </div>

                <flux:input name="industry" :label="__('Industry (optional)')" :value="old('industry')" />
                <flux:input name="source" :label="__('Lead source (optional)')" :value="old('source')" />
                <flux:input name="ownership_type" :label="__('Ownership type (optional)')" :value="old('ownership_type')" />
                <flux:input name="founded_year" :label="__('Founded year (optional)')" type="number" min="1600" max="{{ now()->year }}" :value="old('founded_year')" />

                <flux:input name="employee_count" :label="__('Employee count (optional)')" type="number" min="1" :value="old('employee_count')" />
                <flux:input name="annual_revenue" :label="__('Annual revenue (optional)')" type="number" min="0" step="0.01" :value="old('annual_revenue')" />
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Contact & Channels') }}</flux:heading>
                <flux:subheading>{{ __('Company communication channels and billing/support details.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input name="email" :label="__('Company email (optional)')" type="email" :value="old('email')" />
                <flux:input name="billing_email" :label="__('Billing email (optional)')" type="email" :value="old('billing_email')" />

                <flux:input name="phone" :label="__('Company phone (optional)')" :value="old('phone')" />
                <flux:input name="support_phone" :label="__('Support phone (optional)')" :value="old('support_phone')" />

                <flux:input name="website" :label="__('Website (optional)')" type="url" :value="old('website')" placeholder="example.com" />
                <flux:input name="linkedin_url" :label="__('LinkedIn URL (optional)')" type="url" :value="old('linkedin_url')" />

                <flux:input name="timezone" :label="__('Timezone (optional)')" :value="old('timezone')" placeholder="UTC" />
                <flux:input name="tax_id" :label="__('Tax ID (optional)')" :value="old('tax_id')" />

                <div class="md:col-span-2">
                    <flux:radio.group name="preferred_contact_method" :label="__('Preferred contact method (optional)')" variant="segmented">
                        @foreach ($this->preferredContactMethods as $method)
                            <flux:radio value="{{ $method }}" :checked="old('preferred_contact_method') === $method">
                                {{ \Illuminate\Support\Str::headline($method) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Primary Contact') }}</flux:heading>
                    <flux:text class="mt-2 text-xs">
                        {{ __('Save the company first, then assign a primary contact from linked contacts on the edit page.') }}
                    </flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Address, Follow-up & Notes') }}</flux:heading>
                <flux:subheading>{{ __('Location details and CRM timeline fields.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input name="address_line_1" :label="__('Address line 1 (optional)')" :value="old('address_line_1')" />
                <flux:input name="address_line_2" :label="__('Address line 2 (optional)')" :value="old('address_line_2')" />
                <flux:input name="city" :label="__('City (optional)')" :value="old('city')" />
                <flux:input name="state" :label="__('State / Region (optional)')" :value="old('state')" />
                <flux:input name="postal_code" :label="__('Postal code (optional)')" :value="old('postal_code')" />
                <flux:input name="country" :label="__('Country (optional)')" :value="old('country')" />

                <flux:input
                    name="last_contacted_at"
                    :label="__('Last contacted date (optional)')"
                    type="date"
                    :value="old('last_contacted_at')"
                />
                <flux:input
                    name="next_follow_up_at"
                    :label="__('Next follow-up date (optional)')"
                    type="date"
                    :value="old('next_follow_up_at')"
                />

                <div class="md:col-span-2">
                    <flux:textarea name="notes" :label="__('Notes (optional)')" rows="5">{{ old('notes') }}</flux:textarea>
                </div>
            </div>
        </flux:card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <flux:button variant="ghost" :href="route('companies.index', $indexQuery)" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit">
                {{ __('Create Company') }}
            </flux:button>
        </div>
    </form>
</div>
