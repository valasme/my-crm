<?php

use App\Models\Deal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Deal Details")] class extends Component {
    public Deal $deal;

    /**
     * Mount the component.
     */
    public function mount(Deal $deal): void
    {
        if ((int) $deal->user_id !== (int) Auth::id()) {
            abort(404);
        }

        $this->deal = $deal->loadMissing([
            "company:id,name,user_id",
            "contact:id,name,user_id",
            "activity:id,name,user_id,activity_at,status,type",
        ]);

        Gate::authorize("view", $this->deal);
    }

    /**
     * @return Collection<int, Deal>
     */
    #[Computed]
    public function relatedTimeline(): Collection
    {
        if (
            $this->deal->activity_id === null &&
            $this->deal->company_id === null &&
            $this->deal->contact_id === null
        ) {
            return new Collection();
        }

        return Deal::query()
            ->where("user_id", Auth::id())
            ->whereKeyNot($this->deal->id)
            ->where(function ($query): void {
                if ($this->deal->activity_id !== null) {
                    $query->where("activity_id", $this->deal->activity_id);
                }

                if ($this->deal->contact_id !== null) {
                    if ($this->deal->activity_id !== null) {
                        $query->orWhere("contact_id", $this->deal->contact_id);
                    } else {
                        $query->where("contact_id", $this->deal->contact_id);
                    }
                }

                if ($this->deal->company_id !== null) {
                    if (
                        $this->deal->activity_id !== null ||
                        $this->deal->contact_id !== null
                    ) {
                        $query->orWhere("company_id", $this->deal->company_id);
                    } else {
                        $query->where("company_id", $this->deal->company_id);
                    }
                }
            })
            ->with([
                "company:id,name,user_id",
                "contact:id,name,user_id",
                "activity:id,name,user_id",
            ])
            ->select([
                "id",
                "company_id",
                "contact_id",
                "activity_id",
                "name",
                "type",
                "status",
                "deal_at",
                "next_follow_up_at",
            ])
            ->orderByDesc("deal_at")
            ->orderByDesc("id")
            ->limit(12)
            ->get();
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
                'activity',
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
            <div class="space-y-2">
                <flux:heading size="xl" as="h1" id="show-deal-heading" aria-label="{{ __('Show Deal') }}">
                    {{ $deal->name }}
                </flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge variant="solid">
                        {{ \Illuminate\Support\Str::headline($deal->status) }}
                    </flux:badge>

                    <flux:badge>
                        {{ \Illuminate\Support\Str::headline($deal->type) }}
                    </flux:badge>

                    <flux:badge>{{ $deal->is_active ? __('Active') : __('Inactive') }}</flux:badge>

                    @if ($deal->company)
                        <flux:badge>{{ $deal->company->name }}</flux:badge>
                    @endif

                    @if ($deal->contact)
                        <flux:badge>{{ $deal->contact->name }}</flux:badge>
                    @endif

                    @if ($deal->activity)
                        <flux:badge>{{ $deal->activity->name }}</flux:badge>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="primary" :href="route('deals.edit', ['deal' => $deal, ...$indexQuery])" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>

                <form
                    method="POST"
                    action="{{ route('deals.destroy', $deal) }}"
                    onsubmit="return confirm(@js(__('Delete this deal? This action cannot be undone.')));"
                    class="inline-flex"
                >
                    @csrf
                    @method('DELETE')
                    <flux:button variant="ghost" type="submit" aria-label="{{ __('Delete :deal', ['deal' => $deal->name]) }}">
                        <flux:icon.trash variant="micro" />
                    </flux:button>
                </form>

                <flux:button variant="ghost" :href="route('deals.index', $indexQuery)" wire:navigate>
                    {{ __('Deals') }}
                </flux:button>
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('deals.index', $indexQuery)" wire:navigate>
                <flux:icon.arrow-left variant="micro" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <div role="status" aria-live="polite" aria-atomic="true">
            <flux:badge variant="solid">{{ session('status') }}</flux:badge>
        </div>
    @endif

    @if ($errors->has('deal'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('deal') }}</flux:badge>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading>{{ __('Deal Details') }}</flux:heading>

            <div class="mt-4 grid gap-3 text-sm">
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Type') }}</flux:text>
                    <flux:text>{{ \Illuminate\Support\Str::headline($deal->type) }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Status') }}</flux:text>
                    <flux:text>{{ \Illuminate\Support\Str::headline($deal->status) }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Source') }}</flux:text>
                    <flux:text>{{ $deal->source ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Deal date') }}</flux:text>
                    <flux:text>{{ $deal->deal_at?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Next follow-up') }}</flux:text>
                    <flux:text>{{ $deal->next_follow_up_at?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Outcome') }}</flux:text>
                    <flux:text>{{ $deal->outcome ?: '—' }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading>{{ __('Record Context') }}</flux:heading>

            <div class="mt-4 grid gap-3 text-sm">
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Company') }}</flux:text>
                    <flux:text>
                        @if ($deal->company)
                            <a href="{{ route('companies.show', $deal->company) }}" wire:navigate class="underline">
                                {{ $deal->company->name }}
                            </a>
                        @else
                            —
                        @endif
                    </flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Contact') }}</flux:text>
                    <flux:text>
                        @if ($deal->contact)
                            <a href="{{ route('contacts.show', $deal->contact) }}" wire:navigate class="underline">
                                {{ $deal->contact->name }}
                            </a>
                        @else
                            —
                        @endif
                    </flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Activity') }}</flux:text>
                    <flux:text>
                        @if ($deal->activity)
                            <a href="{{ route('activities.show', $deal->activity) }}" wire:navigate class="underline">
                                {{ $deal->activity->name }}
                            </a>
                        @else
                            —
                        @endif
                    </flux:text>
                </div>
                <div class="pt-2 flex flex-wrap gap-2">
                    <flux:button
                        variant="ghost"
                        :href="route('deals.create', array_filter(['company_id' => $deal->company_id, 'contact_id' => $deal->contact_id, 'activity_id' => $deal->activity_id]))"
                        wire:navigate
                    >
                        {{ __('Create related deal') }}
                    </flux:button>

                    <flux:button
                        variant="ghost"
                        :href="route('activities.create', array_filter(['company_id' => $deal->company_id, 'contact_id' => $deal->contact_id]))"
                        wire:navigate
                    >
                        {{ __('Log activity') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <div class="flex items-center justify-between gap-2">
            <flux:heading>{{ __('Related Deal Timeline') }}</flux:heading>
            <flux:text>{{ __('Most recent deals linked to the same records') }}</flux:text>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($this->relatedTimeline as $timelineItem)
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:link :href="route('deals.show', $timelineItem)" wire:navigate>
                            {{ $timelineItem->name }}
                        </flux:link>

                        <div class="flex items-center gap-2">
                            <flux:badge>{{ \Illuminate\Support\Str::headline($timelineItem->type) }}</flux:badge>
                            <flux:badge>{{ \Illuminate\Support\Str::headline($timelineItem->status) }}</flux:badge>
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Date: :date', ['date' => $timelineItem->deal_at?->format('M d, Y') ?: '—']) }}
                        ·
                        {{ __('Next follow-up: :date', ['date' => $timelineItem->next_follow_up_at?->format('M d, Y') ?: '—']) }}
                        ·
                        {{ __('Activity: :activity', ['activity' => $timelineItem->activity?->name ?: '—']) }}
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No related deals yet.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    <flux:card>
        <flux:heading>{{ __('Notes') }}</flux:heading>
        <flux:text class="mt-4 whitespace-pre-line">{{ $deal->notes ?: __('No notes yet.') }}</flux:text>
    </flux:card>
</div>
