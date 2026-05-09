<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Edit Contact")] class extends Component {
    public Contact $contact;

    /**
     * Mount the component.
     */
    public function mount(Contact $contact): void
    {
        if ((int) $contact->user_id !== (int) Auth::id()) {
            abort(404);
        }

        $this->contact = $contact;

        Gate::authorize("update", $this->contact);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return Contact::statuses();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function preferredContactMethods(): array
    {
        return Contact::preferredContactMethods();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function companies(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user
            ->companies()
            ->select(["id", "name"])
            ->orderBy("name")
            ->pluck("name", "id")
            ->mapWithKeys(
                fn(string $name, int $id): array => [(string) $id => $name],
            )
            ->all();
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
                'company',
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
                <flux:heading size="xl" as="h1" id="edit-contact-heading" aria-label="{{ __('Edit Contact') }}">
                    {{ __('Edit Contact') }}
                </flux:heading>
                <flux:subheading>{{ $contact->name }}</flux:subheading>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" :href="route('contacts.show', ['contact' => $contact, ...$indexQuery])" wire:navigate>
                    {{ __('View') }}
                </flux:button>
                <flux:button variant="ghost" :href="route('contacts.index', $indexQuery)" wire:navigate>
                    {{ __('Contacts') }}
                </flux:button>
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('contacts.show', ['contact' => $contact, ...$indexQuery])" wire:navigate>
                <flux:icon.arrow-left variant="micro" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    @if ($errors->has('contact'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('contact') }}</flux:badge>
        </div>
    @endif

    <form method="POST" action="{{ route('contacts.update', $contact) }}" class="space-y-6" novalidate>
        @csrf
        @method('PUT')

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Contact Profile') }}</flux:heading>
                <flux:subheading>{{ __('Core identity, company relationship, and lifecycle state.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="company_id" class="mb-1 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('Company (optional)') }}
                    </label>
                    <select
                        id="company_id"
                        name="company_id"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-500 dark:focus:ring-zinc-700"
                    >
                        <option value="">{{ __('No company') }}</option>
                        @foreach ($this->companies as $companyId => $companyName)
                            <option
                                value="{{ $companyId }}"
                                @selected((string) old('company_id', (string) $contact->company_id) === $companyId)
                            >
                                {{ $companyName }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input
                    name="name"
                    :label="__('Contact name')"
                    :value="old('name', $contact->name)"
                    required
                    autofocus
                />
                <flux:input name="job_title" :label="__('Job title (optional)')" :value="old('job_title', $contact->job_title)" />

                <div class="md:col-span-2">
                    <flux:radio.group name="status" :label="__('Status')" variant="segmented" required>
                        @foreach ($this->statuses as $status)
                            <flux:radio
                                value="{{ $status }}"
                                :checked="old('status', $contact->status) === $status"
                            >
                                {{ \Illuminate\Support\Str::headline($status) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2">
                    <flux:radio.group name="is_active" :label="__('Contact state')" variant="segmented" required>
                        <flux:radio
                            value="1"
                            :checked="(string) old('is_active', $contact->is_active ? '1' : '0') === '1'"
                        >
                            {{ __('Active') }}
                        </flux:radio>
                        <flux:radio
                            value="0"
                            :checked="(string) old('is_active', $contact->is_active ? '1' : '0') === '0'"
                        >
                            {{ __('Inactive') }}
                        </flux:radio>
                    </flux:radio.group>
                </div>

                <flux:input name="department" :label="__('Department (optional)')" :value="old('department', $contact->department)" />
                <flux:input name="source" :label="__('Lead source (optional)')" :value="old('source', $contact->source)" />
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Communication & Channels') }}</flux:heading>
                <flux:subheading>{{ __('How to reach this contact and preferred outreach method.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input name="email" :label="__('Primary email (optional)')" type="email" :value="old('email', $contact->email)" />
                <flux:input
                    name="alternate_email"
                    :label="__('Alternate email (optional)')"
                    type="email"
                    :value="old('alternate_email', $contact->alternate_email)"
                />

                <flux:input name="phone" :label="__('Phone (optional)')" :value="old('phone', $contact->phone)" />
                <flux:input
                    name="mobile_phone"
                    :label="__('Mobile phone (optional)')"
                    :value="old('mobile_phone', $contact->mobile_phone)"
                />

                <flux:input
                    name="linkedin_url"
                    :label="__('LinkedIn URL (optional)')"
                    type="url"
                    :value="old('linkedin_url', $contact->linkedin_url)"
                />
                <flux:input name="timezone" :label="__('Timezone (optional)')" :value="old('timezone', $contact->timezone)" />

                <div class="md:col-span-2">
                    <flux:radio.group name="preferred_contact_method" :label="__('Preferred contact method (optional)')" variant="segmented">
                        @foreach ($this->preferredContactMethods as $method)
                            <flux:radio
                                value="{{ $method }}"
                                :checked="old('preferred_contact_method', $contact->preferred_contact_method) === $method"
                            >
                                {{ \Illuminate\Support\Str::headline($method) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>
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
                    :value="old('address_line_1', $contact->address_line_1)"
                />
                <flux:input
                    name="address_line_2"
                    :label="__('Address line 2 (optional)')"
                    :value="old('address_line_2', $contact->address_line_2)"
                />
                <flux:input name="city" :label="__('City (optional)')" :value="old('city', $contact->city)" />
                <flux:input name="state" :label="__('State / Region (optional)')" :value="old('state', $contact->state)" />
                <flux:input
                    name="postal_code"
                    :label="__('Postal code (optional)')"
                    :value="old('postal_code', $contact->postal_code)"
                />
                <flux:input name="country" :label="__('Country (optional)')" :value="old('country', $contact->country)" />

                <flux:input
                    name="birthday"
                    :label="__('Birthday (optional)')"
                    type="date"
                    :value="old('birthday', $contact->birthday?->format('Y-m-d'))"
                />
                <flux:input
                    name="last_contacted_at"
                    :label="__('Last contacted date (optional)')"
                    type="date"
                    :value="old('last_contacted_at', $contact->last_contacted_at?->format('Y-m-d'))"
                />
                <flux:input
                    name="next_follow_up_at"
                    :label="__('Next follow-up date (optional)')"
                    type="date"
                    :value="old('next_follow_up_at', $contact->next_follow_up_at?->format('Y-m-d'))"
                />

                <div class="md:col-span-2">
                    <flux:textarea name="notes" :label="__('Notes (optional)')" rows="5">{{ old('notes', $contact->notes) }}</flux:textarea>
                </div>
            </div>
        </flux:card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <flux:button variant="ghost" :href="route('contacts.show', ['contact' => $contact, ...$indexQuery])" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit">
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </form>
</div>
