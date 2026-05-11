<?php

use App\Models\Contact;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title("Contact Details")] class extends Component {
    public Contact $contact;

    /**
     * Mount the component.
     */
    public function mount(Contact $contact): void
    {
        if ((int) $contact->user_id !== (int) Auth::id()) {
            abort(404);
        }

        $this->contact = $contact->loadMissing("company:id,name,user_id");

        Gate::authorize("view", $this->contact);
    }

    /**
     * @return Collection<int, \App\Models\Activity>
     */
    #[Computed]
    public function recentActivities(): Collection
    {
        return $this->contact
            ->activities()
            ->with("company:id,name,user_id")
            ->select([
                "id",
                "company_id",
                "name",
                "type",
                "status",
                "activity_at",
            ])
            ->orderByDesc("activity_at")
            ->orderByDesc("id")
            ->limit(12)
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function recentTasks(): Collection
    {
        return $this->contact
            ->tasks()
            ->with(["company:id,name,user_id", "activity:id,name,user_id"])
            ->select([
                "id",
                "company_id",
                "activity_id",
                "name",
                "type",
                "status",
                "task_at",
                "next_follow_up_at",
            ])
            ->orderByDesc("task_at")
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
            <div class="space-y-2">
                <flux:heading size="xl" as="h1" id="show-contact-heading" aria-label="{{ __('Show Contact') }}">
                    {{ $contact->name }}
                </flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge variant="solid">
                        {{ \Illuminate\Support\Str::headline($contact->status) }}
                    </flux:badge>

                    <flux:badge>{{ $contact->is_active ? __('Active') : __('Inactive') }}</flux:badge>

                    @if ($contact->company)
                        <flux:badge>{{ $contact->company->name }}</flux:badge>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button variant="primary" :href="route('contacts.edit', ['contact' => $contact, ...$indexQuery])" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>

                <form
                    method="POST"
                    action="{{ route('contacts.destroy', $contact) }}"
                    onsubmit="return confirm(@js(__('Delete this contact? This action cannot be undone.')));"
                    class="inline-flex"
                >
                    @csrf
                    @method('DELETE')
                    <flux:button variant="ghost" type="submit" aria-label="{{ __('Delete :contact', ['contact' => $contact->name]) }}">
                        <flux:icon.trash variant="micro" />
                    </flux:button>
                </form>

                <flux:button variant="ghost" :href="route('contacts.index', $indexQuery)" wire:navigate>
                    {{ __('Contacts') }}
                </flux:button>
            </div>
        </div>

        <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" :href="route('contacts.index', $indexQuery)" wire:navigate>
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

    @if ($errors->has('contact'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('contact') }}</flux:badge>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card>
            <flux:heading>{{ __('Contact Information') }}</flux:heading>

            <div class="mt-4 grid gap-3 text-sm">
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Company') }}</flux:text>
                    <flux:text>{{ $contact->company?->name ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Job title') }}</flux:text>
                    <flux:text>{{ $contact->job_title ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Department') }}</flux:text>
                    <flux:text>{{ $contact->department ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Lead source') }}</flux:text>
                    <flux:text>{{ $contact->source ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Birthday') }}</flux:text>
                    <flux:text>{{ $contact->birthday?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Last contacted') }}</flux:text>
                    <flux:text>{{ $contact->last_contacted_at?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Next follow-up') }}</flux:text>
                    <flux:text>{{ $contact->next_follow_up_at?->format('M d, Y') ?: '—' }}</flux:text>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading>{{ __('Communication Information') }}</flux:heading>

            <div class="mt-4 grid gap-3 text-sm">
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Primary email') }}</flux:text>
                    <flux:text>{{ $contact->email ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Alternate email') }}</flux:text>
                    <flux:text>{{ $contact->alternate_email ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Phone') }}</flux:text>
                    <flux:text>{{ $contact->phone ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Mobile phone') }}</flux:text>
                    <flux:text>{{ $contact->mobile_phone ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Preferred contact') }}</flux:text>
                    <flux:text>{{ $contact->preferred_contact_method ? \Illuminate\Support\Str::headline($contact->preferred_contact_method) : '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('Timezone') }}</flux:text>
                    <flux:text>{{ $contact->timezone ?: '—' }}</flux:text>
                </div>
                <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                    <flux:text>{{ __('LinkedIn') }}</flux:text>
                    <flux:text>{{ $contact->linkedin_url ?: '—' }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading>{{ __('Address') }}</flux:heading>

        <div class="mt-4 grid gap-2 text-sm">
            <flux:text>{{ $contact->address_line_1 ?: '—' }}</flux:text>
            @if ($contact->address_line_2)
                <flux:text>{{ $contact->address_line_2 }}</flux:text>
            @endif
            <flux:text>
                {{ collect([$contact->city, $contact->state, $contact->postal_code])->filter()->implode(', ') ?: '—' }}
            </flux:text>
            <flux:text>{{ $contact->country ?: '—' }}</flux:text>
        </div>
    </flux:card>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading>{{ __('Activity Timeline') }}</flux:heading>
            <flux:button
                variant="ghost"
                :href="route('activities.create', array_filter(['company_id' => $contact->company_id, 'contact_id' => $contact->id]))"
                wire:navigate
            >
                {{ __('Log activity') }}
            </flux:button>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($this->recentActivities as $timelineItem)
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
                        {{ __('Company: :company', ['company' => $timelineItem->company?->name ?: '—']) }}
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No activities logged for this contact yet.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading>{{ __('Task Pipeline') }}</flux:heading>
            <flux:button
                variant="ghost"
                :href="route('tasks.create', array_filter(['company_id' => $contact->company_id, 'contact_id' => $contact->id]))"
                wire:navigate
            >
                {{ __('Create task') }}
            </flux:button>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($this->recentTasks as $timelineItem)
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:link :href="route('tasks.show', $timelineItem)" wire:navigate>
                            {{ $timelineItem->name }}
                        </flux:link>

                        <div class="flex items-center gap-2">
                            <flux:badge>{{ \Illuminate\Support\Str::headline($timelineItem->type) }}</flux:badge>
                            <flux:badge>{{ \Illuminate\Support\Str::headline($timelineItem->status) }}</flux:badge>
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Date: :date', ['date' => $timelineItem->task_at?->format('M d, Y') ?: '—']) }}
                        ·
                        {{ __('Company: :company', ['company' => $timelineItem->company?->name ?: '—']) }}
                        ·
                        {{ __('Activity: :activity', ['activity' => $timelineItem->activity?->name ?: '—']) }}
                        ·
                        {{ __('Next follow-up: :date', ['date' => $timelineItem->next_follow_up_at?->format('M d, Y') ?: '—']) }}
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No tasks created for this contact yet.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    <flux:card>
        <flux:heading>{{ __('Notes') }}</flux:heading>
        <flux:text class="mt-4 whitespace-pre-line">{{ $contact->notes ?: __('No notes yet.') }}</flux:text>
    </flux:card>
</div>
