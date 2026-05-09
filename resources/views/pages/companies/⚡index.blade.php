<?php

use App\Models\Company;
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

new #[Title("Companies")] class extends Component {
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
        "status",
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

    #[Url]
    public string $status = "all";

    #[Url]
    public string $active = "all";

    #[Url(as: "follow_up")]
    public string $followUp = "all";

    #[Url]
    public string $sort = "updated_at";

    #[Url]
    public string $direction = "desc";

    #[Url(as: "per_page")]
    public int $perPage = 15;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize("viewAny", Company::class);

        $this->sanitizeFilters();
    }

    /**
     * @return array{search:string,status:string,active:string,follow_up:string,sort:string,direction:string,per_page:int}
     */
    #[Computed]
    public function filters(): array
    {
        return [
            "search" => $this->search,
            "status" => $this->status,
            "active" => $this->active,
            "follow_up" => $this->followUp,
            "sort" => $this->sort,
            "direction" => $this->direction,
            "per_page" => $this->perPage,
        ];
    }

    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        /** @var User $user */
        $user = Auth::user();

        $searchTerms = collect(explode(" ", $this->search))
            ->map(fn(string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $today = now()->toDateString();

        $companies = $user
            ->companies()
            ->select([
                "id",
                "name",
                "industry",
                "status",
                "is_active",
                "primary_contact_name",
                "primary_contact_email",
                "preferred_contact_method",
                "next_follow_up_at",
                "updated_at",
            ])
            ->when($this->status !== "all", function (Builder $query): void {
                $query->where("status", $this->status);
            })
            ->when($this->active !== "all", function (Builder $query): void {
                $query->where("is_active", $this->active === "active");
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
                            ->orWhere("legal_name", "like", $likeTerm)
                            ->orWhere("industry", "like", $likeTerm)
                            ->orWhere("source", "like", $likeTerm)
                            ->orWhere("primary_contact_name", "like", $likeTerm)
                            ->orWhere(
                                "primary_contact_email",
                                "like",
                                $likeTerm,
                            )
                            ->orWhere("email", "like", $likeTerm)
                            ->orWhere("phone", "like", $likeTerm)
                            ->orWhere("status", "like", $likeTerm)
                            ->orWhere("city", "like", $likeTerm)
                            ->orWhere("country", "like", $likeTerm);
                    });
                }
            });

        if ($this->sort === "next_follow_up_at") {
            $companies
                ->orderByRaw(
                    "case when next_follow_up_at is null then 1 else 0 end",
                )
                ->orderBy("next_follow_up_at", $this->direction);
        } else {
            $companies->orderBy($this->sort, $this->direction);
        }

        return $companies
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
            collect(Company::statuses())
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
            "name" => __("Name"),
            "status" => __("Status"),
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
    public function activeLabel(): string
    {
        return $this->activeOptions[$this->active] ?? __("All");
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

        $allowedStatuses = array_merge(["all"], Company::statuses());

        if (!in_array($this->status, $allowedStatuses, true)) {
            $this->status = "all";
        }

        if (!in_array($this->active, self::ACTIVE_FILTERS, true)) {
            $this->active = "all";
        }

        if (!in_array($this->followUp, self::FOLLOW_UP_FILTERS, true)) {
            $this->followUp = "all";
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
        $companies = $this->companies;
        $sortOptions = $this->sortOptions;
        $statusOptions = $this->statusOptions;
        $activeOptions = $this->activeOptions;
        $followUpOptions = $this->followUpOptions;

        $withQuery = fn (array $changes): array => array_filter(
            array_merge($filters, $changes),
            fn ($value): bool => $value !== '' && $value !== null,
        );
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <flux:heading size="xl" as="h1" id="companies-heading" aria-label="{{ __('Companies') }}">
                {{ __('Companies') }}
            </flux:heading>
            <flux:subheading>{{ __('Track and manage your company accounts.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" :href="route('companies.create')" wire:navigate>
            {{ __('New Company') }}
        </flux:button>
    </div>

    @if (session('status'))
        <div role="status" aria-live="polite" aria-atomic="true">
            <flux:badge variant="solid">{{ session('status') }}</flux:badge>
        </div>
    @endif

    @if ($errors->has('company'))
        <div role="alert" aria-live="assertive" aria-atomic="true">
            <flux:badge variant="solid">{{ $errors->first('company') }}</flux:badge>
        </div>
    @endif

    <flux:card>
        <form method="GET" action="{{ route('companies.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <input type="hidden" name="active" value="{{ $filters['active'] }}">
            <input type="hidden" name="follow_up" value="{{ $filters['follow_up'] }}">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
            <input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">

            <div class="w-full sm:max-w-md lg:max-w-lg">
                <flux:input
                    name="search"
                    :label="__('Search')"
                    :value="$filters['search']"
                    type="search"
                    :placeholder="__('Search by company, contact, industry, location, or status')"
                    aria-label="{{ __('Search companies') }}"
                />
            </div>

            <div class="flex items-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>

                @if ($filters['search'] !== '')
                    <flux:button :href="route('companies.index', $withQuery(['search' => null]))" variant="ghost" wire:navigate>
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
                                :href="route('companies.index', $withQuery(['status' => $value]))"
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
                        {{ __('Account: :value', ['value' => $this->activeLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($activeOptions as $value => $label)
                            <flux:menu.item
                                :href="route('companies.index', $withQuery(['active' => $value]))"
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
                        {{ __('Follow-up: :value', ['value' => $this->followUpLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($followUpOptions as $value => $label)
                            <flux:menu.item
                                :href="route('companies.index', $withQuery(['follow_up' => $value]))"
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
                                :href="route('companies.index', $withQuery(['sort' => $value]))"
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
                            :href="route('companies.index', $withQuery(['direction' => 'asc']))"
                            :icon="$filters['direction'] === 'asc' ? 'check' : ''"
                            wire:navigate
                        >
                            {{ __('Ascending') }}
                        </flux:menu.item>
                        <flux:menu.item
                            :href="route('companies.index', $withQuery(['direction' => 'desc']))"
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
                                :href="route('companies.index', $withQuery(['per_page' => $perPageOption]))"
                                :icon="(int) $filters['per_page'] === $perPageOption ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $perPageOption }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:button size="sm" variant="ghost" :href="route('companies.index')" wire:navigate>
                    {{ __('Reset all filters') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    <p id="companies-table-description" class="sr-only">
        {{ __('A sortable list of your companies with status, contacts, and follow-up dates.') }}
    </p>

    <flux:table aria-describedby="companies-table-description">
        <flux:table.columns>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Industry') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">{{ __('Primary Contact') }}</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">{{ __('Contact Method') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Next Follow-up') }}</flux:table.column>
            <flux:table.column class="hidden xl:table-cell">{{ __('Updated') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($companies as $company)
                <flux:table.row wire:key="company-row-{{ $company->id }}">
                    <flux:table.cell variant="strong">
                        <div class="space-y-1">
                            <flux:link :href="route('companies.show', $company)" wire:navigate>
                                {{ $company->name }}
                            </flux:link>

                            <div class="space-y-1 text-xs text-zinc-500 md:hidden">
                                <div>{{ $company->industry ?: __('No industry') }}</div>
                                <div>{{ __('Follow-up: :date', ['date' => $company->next_follow_up_at?->format('M d, Y') ?: '—']) }}</div>
                            </div>

                            @if (! $company->is_active)
                                <flux:text class="text-xs">{{ __('Inactive') }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">{{ $company->industry ?: '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>
                            {{ \Illuminate\Support\Str::headline($company->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">
                        <div class="space-y-1">
                            <div>{{ $company->primary_contact_name ?: '—' }}</div>
                            @if ($company->primary_contact_email)
                                <flux:text class="text-xs">{{ $company->primary_contact_email }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden lg:table-cell">
                        {{ $company->preferred_contact_method ? \Illuminate\Support\Str::headline($company->preferred_contact_method) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        {{ $company->next_follow_up_at?->format('M d, Y') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden xl:table-cell">{{ $company->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end text-zinc-300">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                :href="route('companies.show', $company)"
                                aria-label="{{ __('View :company', ['company' => $company->name]) }}"
                                wire:navigate
                            >
                                <flux:icon.eye variant="micro" />
                            </flux:button>

                            <span class="px-1" aria-hidden="true">|</span>

                            <flux:button
                                size="xs"
                                variant="ghost"
                                :href="route('companies.edit', $company)"
                                aria-label="{{ __('Edit :company', ['company' => $company->name]) }}"
                                wire:navigate
                            >
                                <flux:icon.pencil-square variant="micro" />
                            </flux:button>

                            <span class="px-1" aria-hidden="true">|</span>

                            <form
                                method="POST"
                                action="{{ route('companies.destroy', $company) }}"
                                class="inline-flex"
                                onsubmit="return confirm(@js(__('Delete this company? This action cannot be undone.')));"
                            >
                                @csrf
                                @method('DELETE')

                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    type="submit"
                                    aria-label="{{ __('Delete :company', ['company' => $company->name]) }}"
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
                            <flux:text>{{ __('No companies found with the current search/filter settings.') }}</flux:text>
                            <div class="flex flex-wrap justify-center gap-2">
                                <flux:button variant="ghost" :href="route('companies.index')" wire:navigate>
                                    {{ __('Clear filters') }}
                                </flux:button>
                                <flux:button variant="primary" :href="route('companies.create')" wire:navigate>
                                    {{ __('Create your first company') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($companies->hasPages())
        <div class="mt-2">
            {{ $companies->onEachSide(1)->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</div>
