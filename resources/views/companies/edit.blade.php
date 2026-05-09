<x-layouts::app :title="__('Edit :name', ['name' => $company->name])">
    @php
        $historyFallback = route('companies.show', $company);
    @endphp

    <div class="mx-auto flex h-full w-full max-w-[120rem] flex-1 flex-col gap-6 rounded-xl">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1">
                    <flux:heading size="xl" as="h1" id="edit-company-heading" aria-label="{{ __('Edit Company') }}">
                        {{ __('Edit Company') }}
                    </flux:heading>
                    <flux:subheading>{{ $company->name }}</flux:subheading>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" :href="route('companies.show', $company)" wire:navigate>
                        {{ __('View') }}
                    </flux:button>
                    <flux:button variant="ghost" :href="route('companies.index')" wire:navigate>
                        {{ __('Companies') }}
                    </flux:button>
                </div>
            </div>

            <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:button
                    type="button"
                    variant="ghost"
                    onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='{{ $historyFallback }}'; }"
                >
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

        <form method="POST" action="{{ route('companies.update', $company) }}" class="space-y-6" novalidate>
            @csrf
            @method('PUT')

            <flux:card>
                <div class="mb-4 space-y-1">
                    <flux:heading>{{ __('Company Profile') }}</flux:heading>
                    <flux:subheading>{{ __('Core details and account lifecycle information.') }}</flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input
                        name="name"
                        :label="__('Company name')"
                        :value="old('name', $company->name)"
                        required
                        autofocus
                    />
                    <flux:input name="legal_name" :label="__('Legal name (optional)')" :value="old('legal_name', $company->legal_name)" />

                    <div class="md:col-span-2">
                        <flux:radio.group name="status" :label="__('Status')" variant="segmented" required>
                            @foreach ($statuses as $status)
                                <flux:radio
                                    value="{{ $status }}"
                                    :checked="old('status', $company->status) === $status"
                                >
                                    {{ \Illuminate\Support\Str::headline($status) }}
                                </flux:radio>
                            @endforeach
                        </flux:radio.group>
                    </div>

                    <div class="md:col-span-2">
                        <flux:radio.group name="is_active" :label="__('Account state')" variant="segmented" required>
                            <flux:radio
                                value="1"
                                :checked="(string) old('is_active', $company->is_active ? '1' : '0') === '1'"
                            >
                                {{ __('Active') }}
                            </flux:radio>
                            <flux:radio
                                value="0"
                                :checked="(string) old('is_active', $company->is_active ? '1' : '0') === '0'"
                            >
                                {{ __('Inactive') }}
                            </flux:radio>
                        </flux:radio.group>
                    </div>

                    <flux:input name="industry" :label="__('Industry (optional)')" :value="old('industry', $company->industry)" />
                    <flux:input name="source" :label="__('Lead source (optional)')" :value="old('source', $company->source)" />
                    <flux:input name="ownership_type" :label="__('Ownership type (optional)')" :value="old('ownership_type', $company->ownership_type)" />
                    <flux:input
                        name="founded_year"
                        :label="__('Founded year (optional)')"
                        type="number"
                        min="1600"
                        max="{{ now()->year }}"
                        :value="old('founded_year', $company->founded_year)"
                    />

                    <flux:input
                        name="employee_count"
                        :label="__('Employee count (optional)')"
                        type="number"
                        min="1"
                        :value="old('employee_count', $company->employee_count)"
                    />
                    <flux:input
                        name="annual_revenue"
                        :label="__('Annual revenue (optional)')"
                        type="number"
                        min="0"
                        step="0.01"
                        :value="old('annual_revenue', $company->annual_revenue)"
                    />
                </div>
            </flux:card>

            <flux:card>
                <div class="mb-4 space-y-1">
                    <flux:heading>{{ __('Contact & Channels') }}</flux:heading>
                    <flux:subheading>{{ __('Company communication channels and billing/support details.') }}</flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input name="email" :label="__('Company email (optional)')" type="email" :value="old('email', $company->email)" />
                    <flux:input name="billing_email" :label="__('Billing email (optional)')" type="email" :value="old('billing_email', $company->billing_email)" />

                    <flux:input name="phone" :label="__('Company phone (optional)')" :value="old('phone', $company->phone)" />
                    <flux:input
                        name="support_phone"
                        :label="__('Support phone (optional)')"
                        :value="old('support_phone', $company->support_phone)"
                    />

                    <flux:input name="website" :label="__('Website (optional)')" type="url" :value="old('website', $company->website)" />
                    <flux:input
                        name="linkedin_url"
                        :label="__('LinkedIn URL (optional)')"
                        type="url"
                        :value="old('linkedin_url', $company->linkedin_url)"
                    />

                    <flux:input name="timezone" :label="__('Timezone (optional)')" :value="old('timezone', $company->timezone)" />
                    <flux:input name="tax_id" :label="__('Tax ID (optional)')" :value="old('tax_id', $company->tax_id)" />

                    <div class="md:col-span-2">
                        <flux:radio.group name="preferred_contact_method" :label="__('Preferred contact method (optional)')" variant="segmented">
                            @foreach ($preferredContactMethods as $method)
                                <flux:radio
                                    value="{{ $method }}"
                                    :checked="old('preferred_contact_method', $company->preferred_contact_method) === $method"
                                >
                                    {{ \Illuminate\Support\Str::headline($method) }}
                                </flux:radio>
                            @endforeach
                        </flux:radio.group>
                    </div>

                    <div class="md:col-span-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <flux:heading size="lg">{{ __('Primary Contact') }}</flux:heading>
                    </div>

                    <flux:input
                        name="primary_contact_name"
                        :label="__('Contact name (optional)')"
                        :value="old('primary_contact_name', $company->primary_contact_name)"
                    />
                    <flux:input
                        name="primary_contact_email"
                        :label="__('Contact email (optional)')"
                        type="email"
                        :value="old('primary_contact_email', $company->primary_contact_email)"
                    />
                    <flux:input
                        name="primary_contact_phone"
                        :label="__('Contact phone (optional)')"
                        :value="old('primary_contact_phone', $company->primary_contact_phone)"
                    />
                </div>
            </flux:card>

            <flux:card>
                <div class="mb-4 space-y-1">
                    <flux:heading>{{ __('Address, Follow-up & Notes') }}</flux:heading>
                    <flux:subheading>{{ __('Location details and CRM timeline fields.') }}</flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input
                        name="address_line_1"
                        :label="__('Address line 1 (optional)')"
                        :value="old('address_line_1', $company->address_line_1)"
                    />
                    <flux:input
                        name="address_line_2"
                        :label="__('Address line 2 (optional)')"
                        :value="old('address_line_2', $company->address_line_2)"
                    />
                    <flux:input name="city" :label="__('City (optional)')" :value="old('city', $company->city)" />
                    <flux:input name="state" :label="__('State / Region (optional)')" :value="old('state', $company->state)" />
                    <flux:input
                        name="postal_code"
                        :label="__('Postal code (optional)')"
                        :value="old('postal_code', $company->postal_code)"
                    />
                    <flux:input name="country" :label="__('Country (optional)')" :value="old('country', $company->country)" />

                    <flux:input
                        name="last_contacted_at"
                        :label="__('Last contacted date (optional)')"
                        type="date"
                        :value="old('last_contacted_at', $company->last_contacted_at?->format('Y-m-d'))"
                    />
                    <flux:input
                        name="next_follow_up_at"
                        :label="__('Next follow-up date (optional)')"
                        type="date"
                        :value="old('next_follow_up_at', $company->next_follow_up_at?->format('Y-m-d'))"
                    />

                    <div class="md:col-span-2">
                        <flux:textarea name="notes" :label="__('Notes (optional)')" rows="5">{{ old('notes', $company->notes) }}</flux:textarea>
                    </div>
                </div>
            </flux:card>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <flux:button variant="ghost" :href="route('companies.show', $company)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="primary" type="submit">
                    {{ __('Save Changes') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
