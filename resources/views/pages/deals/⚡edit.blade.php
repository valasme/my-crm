<?php

use App\Models\Deal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Edit Deal")] class extends Component {
    public Deal $deal;

    /**
     * Mount the component.
     */
    public function mount(Deal $deal): void
    {
        if ((int) $deal->user_id !== (int) Auth::id()) {
            abort(404);
        }

        $this->deal = $deal;

        Gate::authorize("update", $this->deal);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return Deal::statuses();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function types(): array
    {
        return Deal::types();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function currencies(): array
    {
        return Deal::currencies();
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

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function activities(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user
            ->activities()
            ->select(["id", "name"])
            ->orderByDesc("activity_at")
            ->orderByDesc("id")
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
                <flux:heading size="xl" as="h1" id="edit-deal-heading" aria-label="{{ __('Edit Deal') }}">
                    {{ __('Edit Deal') }}
                </flux:heading>
                <flux:subheading>{{ $deal->name }}</flux:subheading>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" :href="route('deals.show', ['deal' => $deal, ...$indexQuery])" wire:navigate>
                    {{ __('View') }}
                </flux:button>
                <flux:button variant="ghost" :href="route('deals.index', $indexQuery)" wire:navigate>
                    {{ __('Deals') }}
                </flux:button>
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('deals.show', ['deal' => $deal, ...$indexQuery])" wire:navigate>
                <flux:icon.arrow-left variant="micro" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    @if ($errors->has('deal'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('deal') }}</flux:badge>
        </div>
    @endif

    <form method="POST" action="{{ route('deals.update', $deal) }}" class="space-y-6" novalidate>
        @csrf
        @method('PUT')

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Deal Context') }}</flux:heading>
                <flux:subheading>{{ __('Keep this deal linked to the right CRM records and stage.') }}</flux:subheading>
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
                                @selected((string) old('company_id', (string) $deal->company_id) === $companyId)
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
                                @selected((string) old('contact_id', (string) $deal->contact_id) === $contactId)
                            >
                                {{ $contactName }}
                            </option>
                        @endforeach
                    </select>
                    @error('contact_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="activity_id" class="mb-1 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('Related activity (optional)') }}
                    </label>
                    <select
                        id="activity_id"
                        name="activity_id"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-500 dark:focus:ring-zinc-700"
                    >
                        <option value="">{{ __('No related activity') }}</option>
                        @foreach ($this->activities as $activityId => $activityName)
                            <option
                                value="{{ $activityId }}"
                                @selected((string) old('activity_id', (string) $deal->activity_id) === $activityId)
                            >
                                {{ $activityName }}
                            </option>
                        @endforeach
                    </select>
                    @error('activity_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input
                    name="name"
                    :label="__('Deal name')"
                    :value="old('name', $deal->name)"
                    required
                    autofocus
                />
                <flux:input
                    name="source"
                    :label="__('Source (optional)')"
                    :value="old('source', $deal->source)"
                />

                <div class="md:col-span-2">
                    <flux:radio.group name="type" :label="__('Deal type')" variant="segmented" required>
                        @foreach ($this->types as $type)
                            <flux:radio
                                value="{{ $type }}"
                                :checked="old('type', $deal->type) === $type"
                            >
                                {{ \Illuminate\Support\Str::headline($type) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2">
                    <flux:radio.group name="status" :label="__('Pipeline stage')" variant="segmented" required>
                        @foreach ($this->statuses as $status)
                            <flux:radio
                                value="{{ $status }}"
                                :checked="old('status', $deal->status) === $status"
                            >
                                {{ \Illuminate\Support\Str::headline($status) }}
                            </flux:radio>
                        @endforeach
                    </flux:radio.group>
                </div>

                <div class="md:col-span-2">
                    <flux:radio.group name="is_active" :label="__('Record state')" variant="segmented" required>
                        <flux:radio
                            value="1"
                            :checked="(string) old('is_active', $deal->is_active ? '1' : '0') === '1'"
                        >
                            {{ __('Active') }}
                        </flux:radio>
                        <flux:radio
                            value="0"
                            :checked="(string) old('is_active', $deal->is_active ? '1' : '0') === '0'"
                        >
                            {{ __('Inactive') }}
                        </flux:radio>
                    </flux:radio.group>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Revenue & Timeline') }}</flux:heading>
                <flux:subheading>{{ __('Update expected value, confidence, and close dates as this opportunity progresses.') }}</flux:subheading>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    name="amount"
                    :label="__('Amount')"
                    type="number"
                    min="0"
                    step="0.01"
                    :value="old('amount', (string) $deal->amount)"
                    required
                />

                <div>
                    <label for="currency" class="mb-1 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('Currency') }}
                    </label>
                    <select
                        id="currency"
                        name="currency"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-zinc-500 dark:focus:ring-zinc-700"
                    >
                        @foreach ($this->currencies as $currency)
                            <option
                                value="{{ $currency }}"
                                @selected(old('currency', $deal->currency) === $currency)
                            >
                                {{ $currency }}
                            </option>
                        @endforeach
                    </select>
                    @error('currency')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input
                    name="probability"
                    :label="__('Win probability (%)')"
                    type="number"
                    min="0"
                    max="100"
                    :value="old('probability', (string) $deal->probability)"
                    required
                />

                <flux:input
                    name="deal_at"
                    :label="__('Opened date')"
                    type="date"
                    :value="old('deal_at', $deal->deal_at?->format('Y-m-d'))"
                    required
                />

                <flux:input
                    name="expected_close_at"
                    :label="__('Expected close date (optional)')"
                    type="date"
                    :value="old('expected_close_at', $deal->expected_close_at?->format('Y-m-d'))"
                />

                <flux:input
                    name="next_follow_up_at"
                    :label="__('Next follow-up date (optional)')"
                    type="date"
                    :value="old('next_follow_up_at', $deal->next_follow_up_at?->format('Y-m-d'))"
                />

                <flux:input
                    name="closed_at"
                    :label="__('Closed date (optional)')"
                    type="date"
                    :value="old('closed_at', $deal->closed_at?->format('Y-m-d'))"
                />
            </div>
        </flux:card>

        <flux:card>
            <div class="mb-4 space-y-1">
                <flux:heading>{{ __('Outcome & Notes') }}</flux:heading>
                <flux:subheading>{{ __('Keep internal context and summary outcomes aligned with the current stage.') }}</flux:subheading>
            </div>

            <div class="grid gap-4">
                <flux:input
                    name="outcome"
                    :label="__('Outcome (optional)')"
                    :value="old('outcome', $deal->outcome)"
                />

                <flux:textarea name="notes" :label="__('Notes (optional)')" rows="6">{{ old('notes', $deal->notes) }}</flux:textarea>
            </div>
        </flux:card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <flux:button variant="ghost" :href="route('deals.show', ['deal' => $deal, ...$indexQuery])" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>

            <flux:button variant="primary" type="submit">
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </form>
</div>
