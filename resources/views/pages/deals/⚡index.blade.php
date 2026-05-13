<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title("Deals")] class extends Component {
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
        "amount",
        "probability",
        "expected_close_at",
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

    private const MOVE_STAGE_ATTEMPTS_PER_MINUTE = 90;

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
        Gate::authorize("viewAny", Deal::class);

        $this->sanitizeFilters();
    }

    /**
     * Move a deal card to a new stage/position from the Kanban board.
     */
    public function moveDealStage(
        int $dealId,
        string $targetStatus,
        int $targetPosition,
    ): void {
        if (!in_array($targetStatus, Deal::statuses(), true)) {
            return;
        }

        $userId = Auth::id();

        if ($userId === null) {
            return;
        }

        $rateLimitKey = "deals:move-stage:{$userId}";

        if (
            RateLimiter::tooManyAttempts(
                $rateLimitKey,
                self::MOVE_STAGE_ATTEMPTS_PER_MINUTE,
            )
        ) {
            $this->addError(
                "deal",
                __(
                    "You're moving deals too quickly. Please wait a moment and try again.",
                ),
            );

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $targetPosition = max(0, $targetPosition);

        DB::transaction(function () use (
            $dealId,
            $targetStatus,
            $targetPosition,
            $userId,
        ): void {
            $deal = Deal::query()
                ->where("user_id", $userId)
                ->whereKey($dealId)
                ->lockForUpdate()
                ->first();

            if ($deal === null) {
                return;
            }

            Gate::authorize("update", $deal);

            $currentStatus = (string) $deal->status;
            $currentSortOrder = (int) $deal->sort_order;

            $maxTargetSortOrder = Deal::query()
                ->where("user_id", $userId)
                ->where("status", $targetStatus)
                ->whereKeyNot($deal->id)
                ->max("sort_order");

            $maxAllowedTargetPosition =
                $maxTargetSortOrder === null
                    ? 0
                    : ((int) $maxTargetSortOrder) + 1;

            $clampedTargetPosition = min(
                $targetPosition,
                $maxAllowedTargetPosition,
            );

            if ($currentStatus !== $targetStatus) {
                Deal::query()
                    ->where("user_id", $userId)
                    ->where("status", $currentStatus)
                    ->where("sort_order", ">", $currentSortOrder)
                    ->decrement("sort_order");

                Deal::query()
                    ->where("user_id", $userId)
                    ->where("status", $targetStatus)
                    ->where("sort_order", ">=", $clampedTargetPosition)
                    ->increment("sort_order");

                $updates = [
                    "status" => $targetStatus,
                    "sort_order" => $clampedTargetPosition,
                ];

                if (in_array($targetStatus, Deal::closedStatuses(), true)) {
                    $updates["is_active"] = false;
                    $updates["next_follow_up_at"] = null;
                    $updates["closed_at"] =
                        $deal->closed_at?->toDateString() ??
                        now()->toDateString();
                    $updates["probability"] = $targetStatus === "won" ? 100 : 0;
                } else {
                    $updates["closed_at"] = null;

                    if (!$deal->is_active) {
                        $updates["is_active"] = true;
                    }
                }

                $deal->update($updates);

                return;
            }

            if ($clampedTargetPosition === $currentSortOrder) {
                return;
            }

            if ($clampedTargetPosition > $currentSortOrder) {
                Deal::query()
                    ->where("user_id", $userId)
                    ->where("status", $currentStatus)
                    ->where("sort_order", ">", $currentSortOrder)
                    ->where("sort_order", "<=", $clampedTargetPosition)
                    ->decrement("sort_order");
            } else {
                Deal::query()
                    ->where("user_id", $userId)
                    ->where("status", $currentStatus)
                    ->where("sort_order", ">=", $clampedTargetPosition)
                    ->where("sort_order", "<", $currentSortOrder)
                    ->increment("sort_order");
            }

            $deal->update([
                "sort_order" => $clampedTargetPosition,
            ]);
        });

        $this->resetErrorBag("deal");
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
    public function deals(): LengthAwarePaginator
    {
        /** @var User $user */
        $user = Auth::user();

        $deals = $this->filteredDealsQuery($user)
            ->with(["company:id,name,user_id", "contact:id,name,user_id"])
            ->select([
                "id",
                "company_id",
                "contact_id",
                "name",
                "type",
                "status",
                "amount",
                "currency",
                "probability",
                "expected_close_at",
                "next_follow_up_at",
                "is_active",
                "updated_at",
            ]);

        if (
            in_array(
                $this->sort,
                ["expected_close_at", "next_follow_up_at"],
                true,
            )
        ) {
            $deals
                ->orderByRaw("{$this->sort} is null")
                ->orderBy($this->sort, $this->direction);
        } else {
            $deals->orderBy($this->sort, $this->direction);
        }

        return $deals
            ->orderByDesc("id")
            ->paginate($this->perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, Collection<int, Deal>>
     */
    #[Computed]
    public function boardDealsByStatus(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $deals = $this->filteredDealsQuery($user)
            ->with(["company:id,name,user_id", "contact:id,name,user_id"])
            ->select([
                "id",
                "company_id",
                "contact_id",
                "name",
                "status",
                "amount",
                "currency",
                "probability",
                "expected_close_at",
                "next_follow_up_at",
                "sort_order",
                "updated_at",
            ])
            ->orderBy("sort_order")
            ->orderByDesc("updated_at")
            ->orderByDesc("id")
            ->get()
            ->groupBy("status");

        $grouped = [];

        foreach (Deal::statuses() as $status) {
            $grouped[$status] = $deals->get($status, collect());
        }

        return $grouped;
    }

    /**
     * @return array<string, float>
     */
    #[Computed]
    public function stageTotals(): array
    {
        $totals = [];

        foreach ($this->boardDealsByStatus as $status => $deals) {
            $totals[$status] = (float) $deals->sum(
                fn(Deal $deal): float => (float) $deal->amount,
            );
        }

        return $totals;
    }

    #[Computed]
    public function openDealsCount(): int
    {
        $openCount = 0;

        foreach (Deal::statuses() as $status) {
            if (in_array($status, Deal::closedStatuses(), true)) {
                continue;
            }

            $openCount += (
                $this->boardDealsByStatus[$status] ?? collect()
            )->count();
        }

        return $openCount;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statusOptions(): array
    {
        return array_merge(
            ["all" => __("All")],
            collect(Deal::statuses())
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
            collect(Deal::types())
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
            "name" => __("Deal name"),
            "status" => __("Stage"),
            "amount" => __("Amount"),
            "probability" => __("Probability"),
            "expected_close_at" => __("Expected close"),
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

        $allowedStatuses = array_merge(["all"], Deal::statuses());

        if (!in_array($this->status, $allowedStatuses, true)) {
            $this->status = "all";
        }

        $allowedTypes = array_merge(["all"], Deal::types());

        if (!in_array($this->type, $allowedTypes, true)) {
            $this->type = "all";
        }

        if (!in_array($this->active, self::ACTIVE_FILTERS, true)) {
            $this->active = "all";
        }

        if (!in_array($this->followUp, self::FOLLOW_UP_FILTERS, true)) {
            $this->followUp = "all";
        }

        $this->company = $this->sanitizeOwnedFilter(
            $this->company,
            Company::class,
        );
        $this->contact = $this->sanitizeOwnedFilter(
            $this->contact,
            Contact::class,
        );

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

    /**
     * @param  class-string  $model
     */
    private function sanitizeOwnedFilter(string $value, string $model): string
    {
        if ($value === "all") {
            return "all";
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            "options" => ["min_range" => 1],
        ]);

        if ($id === false) {
            return "all";
        }

        /** @var Builder $query */
        $query = call_user_func([$model, "query"]);

        $exists = $query
            ->where("user_id", Auth::id())
            ->whereKey((int) $id)
            ->exists();

        if (!$exists) {
            return "all";
        }

        return (string) $id;
    }

    /**
     * @return Builder<Deal>
     */
    private function filteredDealsQuery(User $user): Builder
    {
        $searchTerms = collect(explode(" ", $this->search))
            ->map(fn(string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $today = now()->toDateString();

        return $user
            ->deals()
            ->getQuery()
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
                            ->orWhere("status", "like", $likeTerm)
                            ->orWhere("type", "like", $likeTerm)
                            ->orWhere("source", "like", $likeTerm)
                            ->orWhere("currency", "like", $likeTerm)
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
    }
};
?>

<div class="mx-auto flex h-full w-full max-w-[120rem] flex-1 flex-col gap-6 rounded-xl">
    @php
        $filters = $this->filters;
        $deals = $this->deals;
        $statusOptions = $this->statusOptions;
        $typeOptions = $this->typeOptions;
        $activeOptions = $this->activeOptions;
        $companyOptions = $this->companyOptions;
        $contactOptions = $this->contactOptions;
        $followUpOptions = $this->followUpOptions;
        $sortOptions = $this->sortOptions;

        $withQuery = fn (array $changes): array => array_filter(
            array_merge($filters, $changes),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        $indexQuery = $withQuery([
            'page' => $deals->currentPage() > 1 ? $deals->currentPage() : null,
        ]);

        $boardDealsByStatus = $this->boardDealsByStatus;
        $stageTotals = $this->stageTotals;
        $openDealsCount = $this->openDealsCount;
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <flux:heading size="xl" as="h1" id="deals-heading" aria-label="{{ __('Deals') }}">
                {{ __('Deals') }}
            </flux:heading>
            <flux:subheading>{{ __('Track opportunities, projected revenue, and stage progression in one pipeline view.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" :href="route('deals.create', $indexQuery)" wire:navigate>
            {{ __('New Deal') }}
        </flux:button>
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

    <flux:card>
        <form method="GET" action="{{ route('deals.index') }}" class="flex flex-wrap items-end gap-3">
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
                    :placeholder="__('Search by deal, stage, type, source, company, or contact')"
                    aria-label="{{ __('Search deals') }}"
                />
            </div>

            <div class="flex items-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>

                @if ($filters['search'] !== '')
                    <flux:button :href="route('deals.index', $withQuery(['search' => null]))" variant="ghost" wire:navigate>
                        {{ __('Clear search') }}
                    </flux:button>
                @endif
            </div>
        </form>

        <div class="mt-6 overflow-x-auto pb-1">
            <div class="flex min-w-max flex-wrap items-center gap-2 lg:min-w-0">
                <flux:dropdown position="bottom" align="start">
                    <flux:button size="sm" variant="ghost">
                        {{ __('Stage: :value', ['value' => $this->statusLabel]) }}
                    </flux:button>

                    <flux:menu>
                        @foreach ($statusOptions as $value => $label)
                            <flux:menu.item
                                :href="route('deals.index', $withQuery(['status' => $value]))"
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
                                :href="route('deals.index', $withQuery(['type' => $value]))"
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
                                :href="route('deals.index', $withQuery(['active' => $value]))"
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
                                :href="route('deals.index', $withQuery(['company' => $value]))"
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
                                :href="route('deals.index', $withQuery(['contact' => $value]))"
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
                                :href="route('deals.index', $withQuery(['follow_up' => $value]))"
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
                                :href="route('deals.index', $withQuery(['sort' => $value]))"
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
                            :href="route('deals.index', $withQuery(['direction' => 'asc']))"
                            :icon="$filters['direction'] === 'asc' ? 'check' : ''"
                            wire:navigate
                        >
                            {{ __('Ascending') }}
                        </flux:menu.item>
                        <flux:menu.item
                            :href="route('deals.index', $withQuery(['direction' => 'desc']))"
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
                                :href="route('deals.index', $withQuery(['per_page' => $perPageOption]))"
                                :icon="(int) $filters['per_page'] === $perPageOption ? 'check' : ''"
                                wire:navigate
                            >
                                {{ $perPageOption }}
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>

                <flux:button size="sm" variant="ghost" :href="route('deals.index')" wire:navigate>
                    {{ __('Reset all filters') }}
                </flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <flux:heading>{{ __('Pipeline Board') }}</flux:heading>
                <flux:text class="mt-1 text-xs">{{ __('Drag a deal card into another stage to update it instantly.') }}</flux:text>
            </div>
            <flux:badge>{{ __('Open Deals: :count', ['count' => $openDealsCount]) }}</flux:badge>
        </div>

        <div class="mt-4 overflow-x-auto pb-2">
            <div class="grid min-w-[72rem] gap-4 xl:min-w-0 xl:grid-cols-6" data-deals-kanban>
                @foreach (Deal::statuses() as $stage)
                    @php
                        $stageDeals = $boardDealsByStatus[$stage] ?? collect();
                        $stageAmount = $stageTotals[$stage] ?? 0;
                    @endphp

                    <div
                        wire:key="deal-stage-{{ $stage }}"
                        class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/50"
                        aria-label="{{ __('Stage :stage', ['stage' => \Illuminate\Support\Str::headline($stage)]) }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <flux:heading size="sm">{{ \Illuminate\Support\Str::headline($stage) }}</flux:heading>
                            <flux:badge>{{ $stageDeals->count() }}</flux:badge>
                        </div>

                        <flux:text class="mt-1 text-xs">
                            {{ number_format($stageAmount, 2) }} {{ __('in pipeline value') }}
                        </flux:text>

                        <div
                            data-deal-stage="{{ $stage }}"
                            class="mt-3 min-h-[8rem] max-h-[32rem] overflow-y-auto overscroll-contain space-y-2 rounded-lg border border-dashed border-zinc-300 p-2 dark:border-zinc-700"
                            aria-label="{{ __('Drop zone for :stage', ['stage' => \Illuminate\Support\Str::headline($stage)]) }}"
                        >
                            @forelse ($stageDeals as $stageDeal)
                                <article
                                    wire:key="deal-board-card-{{ $stageDeal->id }}"
                                    draggable="true"
                                    data-deal-id="{{ $stageDeal->id }}"
                                    aria-grabbed="false"
                                    aria-label="{{ __('Drag :deal to another stage', ['deal' => $stageDeal->name]) }}"
                                    class="group cursor-grab active:cursor-grabbing rounded-lg border border-zinc-200 bg-white p-3 shadow-sm transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-950/60 dark:hover:border-zinc-500"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="space-y-1">
                                            <flux:link :href="route('deals.show', ['deal' => $stageDeal, ...$indexQuery])" wire:navigate>
                                                {{ $stageDeal->name }}
                                            </flux:link>

                                            <flux:text class="text-xs">
                                                {{ $stageDeal->company?->name ?: __('No company') }}
                                                @if ($stageDeal->contact)
                                                    · {{ $stageDeal->contact->name }}
                                                @endif
                                            </flux:text>
                                        </div>

                                        <span class="text-zinc-400 dark:text-zinc-500" aria-hidden="true">⋮⋮</span>
                                    </div>

                                    <div class="mt-2 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-300">
                                        <span>{{ number_format((float) $stageDeal->amount, 2) }} {{ $stageDeal->currency }}</span>
                                        <span>{{ $stageDeal->probability }}%</span>
                                    </div>

                                    <div class="mt-1 space-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        <div>
                                            {{ __('Expected close: :date', ['date' => $stageDeal->expected_close_at?->format('M d, Y') ?: '—']) }}
                                        </div>
                                        <div>
                                            {{ __('Next follow-up: :date', ['date' => $stageDeal->next_follow_up_at?->format('M d, Y') ?: '—']) }}
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-md bg-zinc-100 p-3 text-center text-xs text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                                    {{ __('Drop deals here') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:card>

    <p id="deals-table-description" class="sr-only">
        {{ __('A sortable list of your deals with stage, value, and expected close dates.') }}
    </p>

    <flux:table aria-describedby="deals-table-description">
        <flux:table.columns>
            <flux:table.column>{{ __('Deal') }}</flux:table.column>
            <flux:table.column>{{ __('Stage') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Amount') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Probability') }}</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">{{ __('Expected Close') }}</flux:table.column>
            <flux:table.column class="hidden xl:table-cell">{{ __('Updated') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($deals as $deal)
                <flux:table.row wire:key="deal-row-{{ $deal->id }}">
                    <flux:table.cell variant="strong">
                        <div class="space-y-1">
                            <flux:link :href="route('deals.show', ['deal' => $deal, ...$indexQuery])" wire:navigate>
                                {{ $deal->name }}
                            </flux:link>
                            <flux:text class="text-xs">
                                {{ $deal->company?->name ?: __('No company') }}
                                @if ($deal->contact)
                                    · {{ $deal->contact->name }}
                                @endif
                            </flux:text>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>{{ \Illuminate\Support\Str::headline($deal->status) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        {{ number_format((float) $deal->amount, 2) }} {{ $deal->currency }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        {{ $deal->probability }}%
                    </flux:table.cell>
                    <flux:table.cell class="hidden lg:table-cell">
                        {{ $deal->expected_close_at?->format('M d, Y') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden xl:table-cell">
                        {{ $deal->updated_at->diffForHumans() }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end text-zinc-300">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                :href="route('deals.show', ['deal' => $deal, ...$indexQuery])"
                                aria-label="{{ __('View :deal', ['deal' => $deal->name]) }}"
                                wire:navigate
                            >
                                <flux:icon.eye variant="micro" />
                            </flux:button>

                            <span class="px-1" aria-hidden="true">|</span>

                            <flux:button
                                size="xs"
                                variant="ghost"
                                :href="route('deals.edit', ['deal' => $deal, ...$indexQuery])"
                                aria-label="{{ __('Edit :deal', ['deal' => $deal->name]) }}"
                                wire:navigate
                            >
                                <flux:icon.pencil-square variant="micro" />
                            </flux:button>

                            <span class="px-1" aria-hidden="true">|</span>

                            <form
                                method="POST"
                                action="{{ route('deals.destroy', $deal) }}"
                                class="inline-flex"
                                onsubmit="return confirm(@js(__('Delete this deal? This action cannot be undone.')));"
                            >
                                @csrf
                                @method('DELETE')

                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    type="submit"
                                    aria-label="{{ __('Delete :deal', ['deal' => $deal->name]) }}"
                                >
                                    <flux:icon.trash variant="micro" />
                                </flux:button>
                            </form>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-10 text-center">
                        <div class="space-y-3">
                            <flux:text>{{ __('No deals found with the current search/filter settings.') }}</flux:text>
                            <div class="flex flex-wrap justify-center gap-2">
                                <flux:button variant="ghost" :href="route('deals.index')" wire:navigate>
                                    {{ __('Clear filters') }}
                                </flux:button>
                                <flux:button variant="primary" :href="route('deals.create', $indexQuery)" wire:navigate>
                                    {{ __('Create your first deal') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($deals->hasPages())
        <div class="mt-2">
            {{ $deals->onEachSide(1)->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</div>
