<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Create Activity")] class extends Component {
    public ?int $prefilledCompanyId = null;

    public ?int $prefilledContactId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize("create", Activity::class);

        /** @var User $user */
        $user = Auth::user();

        $companyId = filter_var(
            (string) request()->query("company_id", ""),
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]],
        );

        if (
            $companyId !== false &&
            Company::query()
                ->where("user_id", $user->id)
                ->whereKey($companyId)
                ->exists()
        ) {
            $this->prefilledCompanyId = (int) $companyId;
        }

        $contactId = filter_var(
            (string) request()->query("contact_id", ""),
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]],
        );

        if (
            $contactId !== false &&
            Contact::query()
                ->where("user_id", $user->id)
                ->whereKey($contactId)
                ->exists()
        ) {
            $this->prefilledContactId = (int) $contactId;
        }
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return Activity::statuses();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function types(): array
    {
        return Activity::types();
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

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function contacts(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user
            ->contacts()
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
                'type',
                'active',
                'follow_up',
                'company',
                'contact',
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
                <flux:heading size="xl" as="h1" id="create-activity-heading" aria-label="{{ __('Create Activity') }}">
                    {{ __('Create Activity') }}
                </flux:heading>
                <flux:subheading>{{ __('Log a customer interaction and preserve follow-up context.') }}</flux:subheading>
            </div>

            <flux:button variant="ghost" :href="route('activities.index', $indexQuery)" wire:navigate>
                {{ __('Activities') }}
            </flux:button>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('activities.index', $indexQuery)" wire:navigate>
                <flux:icon.arrow-left variant="micro" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    @if ($errors->has('activity'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('activity') }}</flux:badge>
        </div>
    @endif

    <form method="POST" action="{{ route('activities.store') }}" class="space-y-6" novalidate>
        @csrf

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Interaction Context') }}</flux:heading>
                <flux:subheading>{{ __('Attach this activity to the right records so the timeline stays complete.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
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
                                @selected((string) old('company_id', (string) $this->prefilledCompanyId) === $companyId)
                            >
                                {{ $companyName }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="contact_id" class="mb-1 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('Contact (optional)') }}
                    </label>
                    <select
                        id="contact_id"
                        name="contact_id"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-500 dark:focus:ring-zinc-700"
                    >
                        <option value="">{{ __('No contact') }}</option>
                        @foreach ($this->contacts as $contactId => $contactName)
                            <option
                                value="{{ $contactId }}"
                                @selected((string) old('contact_id', (string) $this->prefilledContactId) === $contactId)
                            >
                                {{ $contactName }}
                            </option>
                        @endforeach
                    </select>
                    @error('contact_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input name="name" :label="__('Activity title')" :value="old('name')" required autofocus />
                <flux:input name="source" :label="__('Channel / source (optional)')" :value="old('source')" placeholder="{{ __('Inbound, referral, event, etc.') }}" />

                <div class="md:col-span-2">
                    <flux:radio.group name="type" :label="__('Type')" variant="segmented" required>
                        @foreach ($this->types as $type)
                            <flux:radio value="{{ $type }}" :checked="old('type', 'call') === $type">
                                {{ \Illuminate\Support\Str::headline($type) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2">
                    <flux:radio.group name="status" :label="__('Status')" variant="segmented" required>
                        @foreach ($this->statuses as $status)
                            <flux:radio value="{{ $status }}" :checked="old('status', 'planned') === $status">
                                {{ \Illuminate\Support\Str::headline($status) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2">
                    <flux:radio.group name="is_active" :label="__('Record state')" variant="segmented" required>
                        <flux:radio value="1" :checked="(string) old('is_active', '1') === '1'">{{ __('Active') }}</flux:radio>
                        <flux:radio value="0" :checked="(string) old('is_active') === '0'">{{ __('Inactive') }}</flux:radio>
                    </flux:radio.group>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Timeline & Follow-up') }}</flux:heading>
                <flux:subheading>{{ __('Capture what happened and what should happen next.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    name="activity_at"
                    :label="__('Activity date')"
                    type="date"
                    :value="old('activity_at', now()->toDateString())"
                    required
                />

                <flux:input
                    name="next_follow_up_at"
                    :label="__('Next follow-up date (optional)')"
                    type="date"
                    :value="old('next_follow_up_at')"
                />

                <flux:input
                    name="outcome"
                    :label="__('Outcome (optional)')"
                    :value="old('outcome')"
                    placeholder="{{ __('What was the result?') }}"
                    class="md:col-span-2"
                />

                <div class="md:col-span-2">
                    <flux:textarea name="notes" :label="__('Notes (optional)')" rows="6">{{ old('notes') }}</flux:textarea>
                </div>
            </div>
        </flux:card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <flux:button variant="ghost" :href="route('activities.index', $indexQuery)" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit">
                {{ __('Create Activity') }}
            </flux:button>
        </div>
    </form>
</div>
