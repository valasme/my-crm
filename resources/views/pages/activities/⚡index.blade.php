<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title("Activities")] class extends Component {
    use WithPagination;

    /**
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [15, 25, 50];

    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = [
        "updated_at",
        "name",
        "type",
        "status",
        "activity_at",
        "next_follow_up_at",
        "created_at",
    ];

    /**
     * @var array<int, string>
     */
    private const SORT_DIRECTIONS = ["asc", "desc"];

    /**
     * @var array<int, string>
     */
    private const ACTIVE_FILTERS = ["all", "active", "inactive"];

    /**
     * @var array<int, string>
     */
    private const FOLLOW_UP_FILTERS = ["all", "due", "upcoming", "none"];

    #[Url]
    public string $search = "";

    #[Url(keep: true)]
    public string $status = "all";

    #[Url(keep: true)]
    public string $type = "all";

    #[Url(keep: true)]
    public string $active = "all";

    #[Url(as: "follow_up", keep: true)]
    public string $followUp = "all";

    #[Url(keep: true)]
    public string $company = "all";

    #[Url(keep: true)]
    public string $contact = "all";

    #[Url(keep: true)]
    public string $sort = "updated_at";

    #[Url(keep: true)]
    public string $direction = "desc";

    #[Url(as: "per_page", keep: true)]
    public int $perPage = 15;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize("viewAny", Activity::class);

        $this->sanitizeFilters();
    }

    /**
     * @return array{search:string,status:string,type:string,active:string,follow_up:string,company:string,contact:string,sort:string,direction:string,per_page:int}
     */
    #[Computed]
    public function filters(): array
    {
        return [
            "search" => $this->search,
            "status" => $this->status,
            "type" => $this->type,
            "active" => $this->active,
            "follow_up" => $this->followUp,
            "company" => $this->company,
            "contact" => $this->contact,
            "sort" => $this->sort,
            "direction" => $this->direction,
            "per_page" => $this->perPage,
        ];
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        /** @var User $user */
        $user = Auth::user();

        $searchTerms = collect(explode(" ", $this->search))
            ->map(fn(string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $today = now()->toDateString();

        $activities = $user
            ->activities()
            ->with(["company:id,name,user_id", "contact:id,name,user_id"])
            ->select([
                "id",
                "company_id",
                "contact_id",
                "name",
                "type",
                "status",
                "source",
                "activity_at",
                "next_follow_up_at",
                "is_active",
                "updated_at",
            ])
            ->when($this->status !== "all", function (Builder $query): void {
                $query->where("status", $this->status);
            })
            ->when($this->type !== "all", function (Builder $query): void {
                $query->where("type", $this->type);
            })
            ->when($this->active !== "all", function (Builder $query): void {
                $query->where("is_active", $this->active === "active");
            })
            ->when($this->company !== "all", function (Builder $query): void {
                $query->where("company_id", (int) $this->company);
            })
            ->when($this->contact !== "all", function (Builder $query): void {
                $query->where("contact_id", (int) $this->contact);
            })
            ->when($this->followUp !== "all", function (Builder $query) use (
                $today,
            ): void {
                match ($this->followUp) {
                    "due" => $query
                        ->whereNotNull("next_follow_up_at")
                        ->whereDate("next_follow_up_at", "<=", $today),
                    "upcoming" => $query
                        ->whereNotNull("next_follow_up_at")
                        ->whereDate("next_follow_up_at", ">", $today),
                    "none" => $query->whereNull("next_follow_up_at"),
                    default => null,
                };
            })
            ->when($searchTerms->isNotEmpty(), function (Builder $query) use (
                $searchTerms,
            ): void {
                foreach ($searchTerms as $term) {
                    $escapedTerm = addcslashes($term, "%_\\");
                    $likeTerm = "%{$escapedTerm}%";

                    $query->where(function (Builder $innerQuery) use (
                        $likeTerm,
                    ): void {
                        $innerQuery
                            ->where("name", "like", $likeTerm)
                            ->orWhere("type", "like", $likeTerm)
                            ->orWhere("status", "like", $likeTerm)
                            ->orWhere("source", "like", $likeTerm)
                            ->orWhere("outcome", "like", $likeTerm)
                            ->orWhere("notes", "like", $likeTerm)
                            ->orWhereHas(
                                "company",
                                fn(
                                    Builder $companyQuery,
                                ): Builder => $companyQuery->where(
                                    "name",
                                    "like",
                                    $likeTerm,
                                ),
                            )
                            ->orWhereHas(
                                "contact",
                                fn(
                                    Builder $contactQuery,
                                ): Builder => $contactQuery->where(
                                    "name",
                                    "like",
                                    $likeTerm,
                                ),
                            );
                    });
                }
            });

        if ($this->sort === "next_follow_up_at") {
            $activities
                ->orderByRaw(
                    "case when next_follow_up_at is null then 1 else 0 end",
                )
                ->orderBy("next_follow_up_at", $this->direction);
        } else {
            $activities->orderBy($this->sort, $this->direction);
        }

        return $activities
            ->orderByDesc("id")
            ->paginate($this->perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return array_merge(
            ["all" => __("All")],
            collect(Activity::statuses())
                ->mapWithKeys(
                    fn(string $status): array => [
                        $status => Str::headline($status),
                    ],
                )
                ->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function typeOptions(): array
    {
        return array_merge(
            ["all" => __("All")],
            collect(Activity::types())
                ->mapWithKeys(
                    fn(string $type): array => [$type => Str::headline($type)],
                )
                ->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function activeOptions(): array
    {
        return [
            "all" => __("All"),
            "active" => __("Active"),
            "inactive" => __("Inactive"),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function companyOptions(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return ["all" => __("All companies")] +
            $user
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
    public function contactOptions(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return ["all" => __("All contacts")] +
            $user
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
    public function followUpOptions(): array
    {
        return [
            "all" => __("All"),
            "due" => __("Due"),
            "upcoming" => __("Upcoming"),
            "none" => __("No date"),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function sortOptions(): array
    {
        return [
            "updated_at" => __("Recently updated"),
            "name" => __("Activity title"),
            "type" => __("Type"),
            "status" => __("Status"),
            "activity_at" => __("Activity date"),
            "next_follow_up_at" => __("Next follow-up"),
            "created_at" => __("Created date"),
        ];
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    #[Computed]
    public function statusLabel(): string
    {
        return $this->statusOptions[$this->status] ?? __("All");
    }

    #[Computed]
    public function typeLabel(): string
    {
        return $this->typeOptions[$this->type] ?? __("All");
    }

    #[Computed]
    public function activeLabel(): string
    {
        return $this->activeOptions[$this->active] ?? __("All");
    }

    #[Computed]
    public function companyLabel(): string
    {
        return $this->companyOptions[$this->company] ?? __("All companies");
    }

    #[Computed]
    public function contactLabel(): string
    {
        return $this->contactOptions[$this->contact] ?? __("All contacts");
    }

    #[Computed]
    public function followUpLabel(): string
    {
        return $this->followUpOptions[$this->followUp] ?? __("All");
    }

    #[Computed]
    public function sortLabel(): string
    {
        return $this->sortOptions[$this->sort] ?? __("Recently updated");
    }

    #[Computed]
    public function directionLabel(): string
    {
        return $this->direction === "asc" ? __("Ascending") : __("Descending");
    }

    /**
     * Normalize and whitelist listing filters.
     */
    private function sanitizeFilters(): void
    {
        $this->search = Str::of(strip_tags($this->search))
            ->squish()
            ->limit(120, "")
            ->toString();

        $allowedStatuses = array_merge(["all"], Activity::statuses());

        if (!in_array($this->status, $allowedStatuses, true)) {
            $this->status = "all";
        }

        $allowedTypes = array_merge(["all"], Activity::types());

        if (!in_array($this->type, $allowedTypes, true)) {
            $this->type = "all";
        }

        if (!in_array($this->active, self::ACTIVE_FILTERS, true)) {
            $this->active = "all";
        }

        if (!in_array($this->followUp, self::FOLLOW_UP_FILTERS, true)) {
            $this->followUp = "all";
        }

        if ($this->company !== "all") {
            $companyId = filter_var($this->company, FILTER_VALIDATE_INT, [
                "options" => ["min_range" => 1],
            ]);

            if ($companyId === false) {
                $this->company = "all";
            } else {
                if (
                    !Company::query()
                        ->where("user_id", Auth::id())
                        ->whereKey((int) $companyId)
                        ->exists()
                ) {
                    $this->company = "all";
                } else {
                    $this->company = (string) $companyId;
                }
            }
        }

        if ($this->contact !== "all") {
            $contactId = filter_var($this->contact, FILTER_VALIDATE_INT, [
                "options" => ["min_range" => 1],
            ]);

            if ($contactId === false) {
                $this->contact = "all";
            } else {
                if (
                    !Contact::query()
                        ->where("user_id", Auth::id())
                        ->whereKey((int) $contactId)
                        ->exists()
                ) {
                    $this->contact = "all";
                } else {
                    $this->contact = (string) $contactId;
                }
            }
        }

        if (!in_array($this->sort, self::SORTABLE_COLUMNS, true)) {
            $this->sort = "updated_at";
        }

        $direction = strtolower($this->direction);

        if (!in_array($direction, self::SORT_DIRECTIONS, true)) {
            $direction = "desc";
        }

        $this->direction = $direction;

        if (!in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 15;
        }
    }
};
?>

<div class="mx-auto flex h-full w-full max-w-[120rem] flex-1 flex-col gap-6 rounded-xl">
    @php
        $filters = $this->filters;
        $activities = $this->activities;
        $sortOptions = $this->sortOptions;
        $statusOptions = $this->statusOptions;
        $typeOptions = $this->typeOptions;
        $activeOptions = $this->activeOptions;
        $companyOptions = $this->companyOptions;
        $contactOptions = $this->contactOptions;
        $followUpOptions = $this->followUpOptions;

        $withQuery = fn (array $changes): array => array_filter(
            array_merge($filters, $changes),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        $indexQuery = $withQuery([
            'page' => $activities->currentPage() > 1 ? $activities->currentPage() : null,
        ]);
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <flux:heading size="xl" as="h1" id="activities-heading" aria-label="{{ __('Activities') }}">
                {{ __('Activities') }}
            </flux:heading>
            <flux:subheading>{{ __('Timeline all customer interactions so follow-up context is never lost.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" :href="route('activities.create', $indexQuery)" wire:navigate>
            {{ __('New Activity') }}
        </flux:button>
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

    <flux:card>
        <form method="GET" action="{{ route('activities.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <input type="hidden" name="type" value="{{ $filters['type'] }}">
            <input type="hidden" name="active" value="{{ $filters['active'] }}">
            <input type="hidden" name="follow_up" value="{{ $filters['follow_up'] }}">
            <input type="hidden" name="company" value="{{ $filters['company'] }}">
            <input type="hidden" name="contact" value="{{ $filters['contact'] }}">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
            <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">

            <div class="w-full sm:max-w-md lg:max-w-lg">
                <flux:input
                    name="search"
                    :label="__('Search')"
                    :value="$filters['search']"
                    type="search"
                    :placeholder="__('Search by title, type, source, outcome, company, or contact')"
                    aria-label="{{ __('Search activities') }}"
                />
            </div>

            <div class="flex items-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>

                @if ($filters['search'] !== '')
                    <flux:button :href="route('activities.index', $withQuery(['search' => null]))" variant="ghost" wire:navigate>
                        {{ __('Clear search') }}
                    </flux:button>
                @endif
            </div>
        </form>

        <div class="mt-6 overflow-x-auto pb-1">
            <div class="flex min-w-max flex-wrap items-center gap-2 lg:min-w-0">
                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Status: :value', ['value' => $this->statusLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($statusOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['status' => $value]))"
                                :icon="$filters['status'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Type: :value', ['value' => $this->typeLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($typeOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['type' => $value]))"
                                :icon="$filters['type'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('State: :value', ['value' => $this->activeLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($activeOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['active' => $value]))"
                                :icon="$filters['active'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Company: :value', ['value' => $this->companyLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($companyOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['company' => $value]))"
                                :icon="$filters['company'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Contact: :value', ['value' => $this->contactLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($contactOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['contact' => $value]))"
                                :icon="$filters['contact'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Follow-up: :value', ['value' => $this->followUpLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($followUpOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['follow_up' => $value]))"
                                :icon="$filters['follow_up'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Sort: :value', ['value' => $this->sortLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($sortOptions as $value => $label)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['sort' => $value]))"
                                :icon="$filters['sort'] === $value ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $label }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Order: :value', ['value' => $this->directionLabel]) }}
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item
                            :href="route('activities.index', $withQuery(['direction' => 'asc']))"
                            :icon="$filters['direction'] === 'asc' ? 'check' : ''"
                            wire:navigate
                        >
                            {{ __('Ascending') }}
                        </flux:menu.item>
                        <flux:menu.item
                            :href="route('activities.index', $withQuery(['direction' => 'desc']))"
                            :icon="$filters['direction'] === 'desc' ? 'check' : ''"
                            wire:navigate
                        >
                            {{ __('Descending') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Rows: :value', ['value' => (int) $filters['per_page']]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($this->perPageOptions as $perPageOption)
                            <flux:menu.item
                                :href="route('activities.index', $withQuery(['per_page' => $perPageOption]))"
                                :icon="(int) $filters['per_page'] === $perPageOption ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $perPageOption }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:button size="sm" variant="ghost" :href="route('activities.index')" wire:navigate>
                    {{ __('Reset all filters') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    <p id="activities-table-description" class="sr-only">
        {{ __('A sortable timeline list of activities linked to your companies and contacts.') }}
    </p>

    <flux:table aria-describedby="activities-table-description">
        <flux:table.columns>
            <flux:table.column>{{ __('Activity') }}</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Related') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Activity Date') }}</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">{{ __('Next Follow-up') }}</flux:table.column>
            <flux:table.column class="hidden xl:table-cell">{{ __('Updated') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($activities as $activity)
                <flux:table.row wire:key="activity-row-{{ $activity->id }}">
                    <flux:table.cell variant="strong">
                        <div class="space-y-1">
                            <flux:link :href="route('activities.show', ['activity' => $activity, ...$indexQuery])" wire:navigate>
                                {{ $activity->name }}
                            </flux:link>

                            <div class="space-y-1 text-xs text-zinc-500 md:hidden">
                                <div>{{ \Illuminate\Support\Str::headline($activity->type) }}</div>
                                <div>{{ $activity->company?->name ?: __('No company') }}</div>
                                <div>{{ $activity->contact?->name ?: __('No contact') }}</div>
                                <div>{{ __('Follow-up: :date', ['date' => $activity->next_follow_up_at?->format('M d, Y') ?: '—']) }}</div>
                            </div>

                            @if (! $activity->is_active)
                                <flux:text class="text-xs">{{ __('Inactive') }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">
                        {{ \Illuminate\Support\Str::headline($activity->type) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>
                            {{ \Illuminate\Support\Str::headline($activity->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        <div class="space-y-1">
                            <div>{{ $activity->company?->name ?: '—' }}</div>
                            <flux:text class="text-xs">{{ $activity->contact?->name ?: '—' }}</flux:text>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        {{ $activity->activity_at?->format('M d, Y') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden lg:table-cell">
                        {{ $activity->next_follow_up_at?->format('M d, Y') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden xl:table-cell">{{ $activity->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end text-zinc-300">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                :href="route('activities.show', ['activity' => $activity, ...$indexQuery])"
                                aria-label="{{ __('View :activity', ['activity' => $activity->name]) }}"
                                wire:navigate
                            >
                                <flux:icon.eye variant="micro" />
                            </flux:button>

                            <span class="px-1" aria-hidden="true">|</span>

                            <flux:button
                                size="xs"
                                variant="ghost"
                                :href="route('activities.edit', ['activity' => $activity, ...$indexQuery])"
                                aria-label="{{ __('Edit :activity', ['activity' => $activity->name]) }}"
                                wire:navigate
                            >
                                <flux:icon.pencil-square variant="micro" />
                            </flux:button>

                            <span class="px-1" aria-hidden="true">|</span>

                            <form
                                method="POST"
                                action="{{ route('activities.destroy', $activity) }}"
                                class="inline-flex"
                                onsubmit="return confirm(@js(__('Delete this activity? This action cannot be undone.')));"
                            >
                                @csrf
                                @method('DELETE')

                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    type="submit"
                                    aria-label="{{ __('Delete :activity', ['activity' => $activity->name]) }}"
                                >
                                    <flux:icon.trash variant="micro" />
                                </flux:button>
                            </form>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="py-10 text-center">
                        <div class="space-y-3">
                            <flux:text>{{ __('No activities found with the current search/filter settings.') }}</flux:text>
                            <div class="flex flex-wrap justify-center gap-2">
                                <flux:button variant="ghost" :href="route('activities.index')" wire:navigate>
                                    {{ __('Clear filters') }}
                                </flux:button>
                                <flux:button variant="primary" :href="route('activities.create', $indexQuery)" wire:navigate>
                                    {{ __('Log your first activity') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($activities->hasPages())
        <div class="mt-2">
            {{ $activities->onEachSide(1)->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</div>
