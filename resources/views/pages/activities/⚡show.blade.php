<?php

use App\Models\Activity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Activity Details")] class extends Component {
    public Activity $activity;

    /**
     * Mount the component.
     */
    public function mount(Activity $activity): void
    {
        if ((int) $activity->user_id !== (int) Auth::id()) {
            abort(404);
        }

        $this->activity = $activity->loadMissing([
            "company:id,name,user_id",
            "contact:id,name,user_id",
        ]);

        Gate::authorize("view", $this->activity);
    }

    /**
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function relatedTimeline(): Collection
    {
        if (
            $this->activity->company_id === null &&
            $this->activity->contact_id === null
        ) {
            return new Collection();
        }

        return Activity::query()
            ->where("user_id", Auth::id())
            ->whereKeyNot($this->activity->id)
            ->where(function ($query): void {
                if ($this->activity->contact_id !== null) {
                    $query->where("contact_id", $this->activity->contact_id);
                }

                if ($this->activity->company_id !== null) {
                    if ($this->activity->contact_id !== null) {
                        $query->orWhere(
                            "company_id",
                            $this->activity->company_id,
                        );
                    } else {
                        $query->where(
                            "company_id",
                            $this->activity->company_id,
                        );
                    }
                }
            })
            ->with(["company:id,name,user_id", "contact:id,name,user_id"])
            ->select([
                "id",
                "company_id",
                "contact_id",
                "name",
                "type",
                "status",
                "activity_at",
                "next_follow_up_at",
            ])
            ->orderByDesc("activity_at")
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
                <flux:heading size="xl" as="h1" id="show-activity-heading" aria-label="{{ __('Show Activity') }}">
                    {{ $activity->name }}
                </flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge variant="solid">
                        {{ \Illuminate\Support\Str::headline($activity->status) }}
                    </flux:badge>

                    <flux:badge>
                        {{ \Illuminate\Support\Str::headline($activity->type) }}
                    </flux:badge>

                    <flux:badge>{{ $activity->is_active ? __('Active') : __('Inactive') }}</flux:badge>

                    @if ($activity->company)
                        <flux:badge>{{ $activity->company->name }}</flux:badge>
                    @endif

                    @if ($activity->contact)
                        <flux:badge>{{ $activity->contact->name }}</flux:badge>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="primary" :href="route('activities.edit', ['activity' => $activity, ...$indexQuery])" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>

                <form
                    method="POST"
                    action="{{ route('activities.destroy', $activity) }}"
                    onsubmit="return confirm(@js(__('Delete this activity? This action cannot be undone.')));"
                    class="inline-flex"
                >
                    @csrf
                    @method('DELETE')
                    <flux:button variant="ghost" type="submit" aria-label="{{ __('Delete :activity', ['activity' => $activity->name]) }}">
                        <flux:icon.trash variant="micro" />
                    </flux:button>
                </form>

                <flux:button variant="ghost" :href="route('activities.index', $indexQuery)" wire:navigate>
                    {{ __('Activities') }}
                </flux:button>
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('activities.index', $indexQuery)" wire:navigate>
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

    @if ($errors->has('activity'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('activity') }}</flux:badge>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading>{{ __('Activity Details') }}</flux:heading>

            <div class="mt-4 grid gap-3 text-sm">
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Type') }}</flux:text>
                    <flux:text>{{ \Illuminate\Support\Str::headline($activity->type) }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Status') }}</flux:text>
                    <flux:text>{{ \Illuminate\Support\Str::headline($activity->status) }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Channel / source') }}</flux:text>
                    <flux:text>{{ $activity->source ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Activity date') }}</flux:text>
                    <flux:text>{{ $activity->activity_at?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Next follow-up') }}</flux:text>
                    <flux:text>{{ $activity->next_follow_up_at?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Outcome') }}</flux:text>
                    <flux:text>{{ $activity->outcome ?: '—' }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading>{{ __('Record Context') }}</flux:heading>

            <div class="mt-4 grid gap-3 text-sm">
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Company') }}</flux:text>
                    <flux:text>
                        @if ($activity->company)
                            <a href="{{ route('companies.show', $activity->company) }}" wire:navigate class="underline">
                                {{ $activity->company->name }}
                            </a>
                        @else
                            —
                        @endif
                    </flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[180px_1fr] sm:gap-3">
                    <flux:text>{{ __('Contact') }}</flux:text>
                    <flux:text>
                        @if ($activity->contact)
                            <a href="{{ route('contacts.show', $activity->contact) }}" wire:navigate class="underline">
                                {{ $activity->contact->name }}
                            </a>
                        @else
                            —
                        @endif
                    </flux:text>
                </div>
                <div class="pt-2">
                    <flux:button
                        variant="ghost"
                        :href="route('activities.create', array_filter(['company_id' => $activity->company_id, 'contact_id' => $activity->contact_id]))"
                        wire:navigate
                    >
                        {{ __('Log follow-up activity') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <div class="flex items-center justify-between gap-2">
            <flux:heading>{{ __('Related Timeline') }}</flux:heading>
            <flux:text>{{ __('Most recent linked interactions') }}</flux:text>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($this->relatedTimeline as $timelineItem)
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:link :href="route('activities.show', $timelineItem)" wire:navigate>
                            {{ $timelineItem->name }}
                        </flux:link>

                        <div class="flex items-center gap-2">
                            <flux:badge>{{ \Illuminate\Support\Str::headline($timelineItem->type) }}</flux:badge>
                            <flux:badge>{{ \Illuminate\Support\Str::headline($timelineItem->status) }}</flux:badge>
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Date: :date', ['date' => $timelineItem->activity_at?->format('M d, Y') ?: '—']) }}
                        ·
                        {{ __('Next follow-up: :date', ['date' => $timelineItem->next_follow_up_at?->format('M d, Y') ?: '—']) }}
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No related timeline entries yet.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    <flux:card>
        <flux:heading>{{ __('Notes') }}</flux:heading>
        <flux:text class="mt-4 whitespace-pre-line">{{ $activity->notes ?: __('No notes yet.') }}</flux:text>
    </flux:card>
</div>
