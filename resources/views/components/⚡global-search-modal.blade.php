<?php

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    private const MIN_QUERY_LENGTH = 2;

    private const MAX_QUERY_LENGTH = 120;

    private const MAX_SEARCH_TERMS = 6;

    private const MAX_RESULTS_PER_GROUP = 5;

    public string $query = "";

    /**
     * @var array<string, array<int, array{title:string,subtitle:string,url:string}>>
     */
    public array $results = [];

    public function mount(): void
    {
        Gate::authorize("viewAny", Company::class);
        Gate::authorize("viewAny", Contact::class);
        Gate::authorize("viewAny", Activity::class);
        Gate::authorize("viewAny", Task::class);
        Gate::authorize("viewAny", Deal::class);

        $this->results = $this->emptyResultGroups();
    }

    public function updatedQuery(string $value): void
    {
        $normalizedQuery = $this->sanitizeQuery($value);

        if (mb_strlen($normalizedQuery) < self::MIN_QUERY_LENGTH) {
            $this->results = $this->emptyResultGroups();

            return;
        }

        $searchTerms = $this->extractSearchTerms($normalizedQuery);

        if ($searchTerms === []) {
            $this->results = $this->emptyResultGroups();

            return;
        }

        $user = $this->authenticatedUser();

        if ($user === null) {
            $this->results = $this->emptyResultGroups();

            return;
        }

        $rateLimitKey = "global-search:" . $user->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            $this->results = $this->emptyResultGroups();

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $this->results = [
            "companies" => $this->searchCompanies($user, $searchTerms),
            "contacts" => $this->searchContacts($user, $searchTerms),
            "activities" => $this->searchActivities($user, $searchTerms),
            "tasks" => $this->searchTasks($user, $searchTerms),
            "deals" => $this->searchDeals($user, $searchTerms),
        ];
    }

    public function clearSearch(): void
    {
        $this->query = "";
        $this->results = $this->emptyResultGroups();
    }

    /**
     * @return array<string, array{label:string, icon:string}>
     */
    public function getCategoryConfigProperty(): array
    {
        return [
            "companies" => [
                "label" => __("Companies"),
                "icon" => "building-office-2",
            ],
            "contacts" => ["label" => __("Contacts"), "icon" => "user-group"],
            "activities" => [
                "label" => __("Activities"),
                "icon" => "calendar-days",
            ],
            "tasks" => [
                "label" => __("Tasks"),
                "icon" => "clipboard-document-list",
            ],
            "deals" => ["label" => __("Deals"), "icon" => "banknotes"],
        ];
    }

    public function getCanSearchProperty(): bool
    {
        return mb_strlen($this->normalizedQuery) >= self::MIN_QUERY_LENGTH;
    }

    public function getHasResultsProperty(): bool
    {
        return $this->totalResults > 0;
    }

    public function getTotalResultsProperty(): int
    {
        return collect($this->results)->sum(
            fn(array $group): int => count($group),
        );
    }

    public function getNormalizedQueryProperty(): string
    {
        return $this->sanitizeQuery($this->query);
    }

    /**
     * @param array<int, string> $searchTerms
     * @return array<int, array{title:string,subtitle:string,url:string}>
     */
    private function searchCompanies(User $user, array $searchTerms): array
    {
        $query = Company::query()
            ->where("user_id", $user->id)
            ->select([
                "id",
                "primary_contact_id",
                "name",
                "legal_name",
                "status",
                "industry",
                "source",
                "email",
                "phone",
                "city",
                "state",
                "country",
                "updated_at",
            ])
            ->with([
                "primaryContact" => fn($contactQuery) => $contactQuery->select([
                    "id",
                    "name",
                    "email",
                    "user_id",
                ]),
            ]);

        $this->applySearchTerms($query, $searchTerms, function (
            Builder $innerQuery,
            string $likeTerm,
        ): void {
            $innerQuery
                ->where("name", "like", $likeTerm)
                ->orWhere("legal_name", "like", $likeTerm)
                ->orWhere("industry", "like", $likeTerm)
                ->orWhere("source", "like", $likeTerm)
                ->orWhere("email", "like", $likeTerm)
                ->orWhere("phone", "like", $likeTerm)
                ->orWhere("status", "like", $likeTerm)
                ->orWhere("city", "like", $likeTerm)
                ->orWhere("state", "like", $likeTerm)
                ->orWhere("country", "like", $likeTerm)
                ->orWhere("notes", "like", $likeTerm)
                ->orWhereHas("primaryContact", function (
                    Builder $contactQuery,
                ) use ($likeTerm): void {
                    $contactQuery
                        ->where("name", "like", $likeTerm)
                        ->orWhere("email", "like", $likeTerm);
                });
        });

        return $query
            ->orderByDesc("updated_at")
            ->orderByDesc("id")
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get()
            ->map(
                fn(Company $company): array => [
                    "title" => $company->name,
                    "subtitle" => $this->buildSubtitle(
                        [
                            Str::headline($company->status),
                            $company->industry,
                            $company->primaryContact?->name,
                            $this->buildLocation(
                                $company->city,
                                $company->country,
                            ),
                        ],
                        __("Company record"),
                    ),
                    "url" => route("companies.show", ["company" => $company]),
                ],
            )
            ->all();
    }

    /**
     * @param array<int, string> $searchTerms
     * @return array<int, array{title:string,subtitle:string,url:string}>
     */
    private function searchContacts(User $user, array $searchTerms): array
    {
        $query = Contact::query()
            ->where("user_id", $user->id)
            ->select([
                "id",
                "company_id",
                "name",
                "job_title",
                "status",
                "department",
                "source",
                "email",
                "alternate_email",
                "phone",
                "mobile_phone",
                "city",
                "state",
                "country",
                "updated_at",
            ])
            ->with([
                "company" => fn($companyQuery) => $companyQuery->select([
                    "id",
                    "name",
                    "legal_name",
                    "user_id",
                ]),
            ]);

        $this->applySearchTerms($query, $searchTerms, function (
            Builder $innerQuery,
            string $likeTerm,
        ): void {
            $innerQuery
                ->where("name", "like", $likeTerm)
                ->orWhere("job_title", "like", $likeTerm)
                ->orWhere("department", "like", $likeTerm)
                ->orWhere("source", "like", $likeTerm)
                ->orWhere("email", "like", $likeTerm)
                ->orWhere("alternate_email", "like", $likeTerm)
                ->orWhere("phone", "like", $likeTerm)
                ->orWhere("mobile_phone", "like", $likeTerm)
                ->orWhere("status", "like", $likeTerm)
                ->orWhere("city", "like", $likeTerm)
                ->orWhere("state", "like", $likeTerm)
                ->orWhere("country", "like", $likeTerm)
                ->orWhere("notes", "like", $likeTerm)
                ->orWhereHas("company", function (Builder $companyQuery) use (
                    $likeTerm,
                ): void {
                    $companyQuery
                        ->where("name", "like", $likeTerm)
                        ->orWhere("legal_name", "like", $likeTerm);
                });
        });

        return $query
            ->orderByDesc("updated_at")
            ->orderByDesc("id")
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get()
            ->map(
                fn(Contact $contact): array => [
                    "title" => $contact->name,
                    "subtitle" => $this->buildSubtitle(
                        [
                            Str::headline($contact->status),
                            $contact->job_title,
                            $contact->company?->name,
                            $contact->email,
                        ],
                        __("Contact record"),
                    ),
                    "url" => route("contacts.show", ["contact" => $contact]),
                ],
            )
            ->all();
    }

    /**
     * @param array<int, string> $searchTerms
     * @return array<int, array{title:string,subtitle:string,url:string}>
     */
    private function searchActivities(User $user, array $searchTerms): array
    {
        $query = Activity::query()
            ->where("activities.user_id", $user->id)
            ->select([
                "activities.id",
                "activities.company_id",
                "activities.contact_id",
                "activities.name",
                "activities.type",
                "activities.status",
                "activities.source",
                "activities.activity_at",
                "activities.updated_at",
            ])
            ->with([
                "company" => fn($companyQuery) => $companyQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
                "contact" => fn($contactQuery) => $contactQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
            ]);

        $booleanQuery = $this->buildFullTextBooleanQuery($searchTerms);

        if ($this->supportsFullTextSearch() && $booleanQuery !== null) {
            $query->leftJoin("companies as activity_companies", function (
                JoinClause $join,
            ) use ($user): void {
                $join
                    ->on("activity_companies.id", "=", "activities.company_id")
                    ->where("activity_companies.user_id", "=", $user->id);
            });

            $query->leftJoin("contacts as activity_contacts", function (
                JoinClause $join,
            ) use ($user): void {
                $join
                    ->on("activity_contacts.id", "=", "activities.contact_id")
                    ->where("activity_contacts.user_id", "=", $user->id);
            });

            $query->where(function (Builder $innerQuery) use (
                $booleanQuery,
            ): void {
                $innerQuery
                    ->whereRaw(
                        "MATCH (activities.name, activities.type, activities.status, activities.source, activities.notes) AGAINST (? IN BOOLEAN MODE)",
                        [$booleanQuery],
                    )
                    ->orWhereRaw(
                        "MATCH (activity_companies.name, activity_companies.legal_name, activity_companies.industry, activity_companies.source, activity_companies.email, activity_companies.phone, activity_companies.status, activity_companies.city, activity_companies.country) AGAINST (? IN BOOLEAN MODE)",
                        [$booleanQuery],
                    )
                    ->orWhereRaw(
                        "MATCH (activity_contacts.name, activity_contacts.email) AGAINST (? IN BOOLEAN MODE)",
                        [$booleanQuery],
                    );
            });
        } else {
            $this->applySearchTerms($query, $searchTerms, function (
                Builder $innerQuery,
                string $likeTerm,
            ): void {
                $innerQuery
                    ->where("activities.name", "like", $likeTerm)
                    ->orWhere("activities.type", "like", $likeTerm)
                    ->orWhere("activities.status", "like", $likeTerm)
                    ->orWhere("activities.source", "like", $likeTerm)
                    ->orWhere("activities.notes", "like", $likeTerm)
                    ->orWhereHas("company", function (
                        Builder $companyQuery,
                    ) use ($likeTerm): void {
                        $companyQuery->where("name", "like", $likeTerm);
                    })
                    ->orWhereHas("contact", function (
                        Builder $contactQuery,
                    ) use ($likeTerm): void {
                        $contactQuery->where("name", "like", $likeTerm);
                    });
            });
        }

        return $query
            ->orderByDesc("activities.updated_at")
            ->orderByDesc("activities.id")
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get()
            ->map(
                fn(Activity $activity): array => [
                    "title" => $activity->name,
                    "subtitle" => $this->buildSubtitle(
                        [
                            Str::headline($activity->type),
                            Str::headline($activity->status),
                            $activity->activity_at?->format("M d, Y"),
                            $activity->company?->name,
                            $activity->contact?->name,
                        ],
                        __("Activity record"),
                    ),
                    "url" => route("activities.show", [
                        "activity" => $activity,
                    ]),
                ],
            )
            ->all();
    }

    /**
     * @param array<int, string> $searchTerms
     * @return array<int, array{title:string,subtitle:string,url:string}>
     */
    private function searchTasks(User $user, array $searchTerms): array
    {
        $query = Task::query()
            ->where("user_id", $user->id)
            ->select([
                "id",
                "company_id",
                "contact_id",
                "activity_id",
                "name",
                "type",
                "status",
                "source",
                "task_at",
                "next_follow_up_at",
                "updated_at",
            ])
            ->with([
                "company" => fn($companyQuery) => $companyQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
                "contact" => fn($contactQuery) => $contactQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
                "activity" => fn($activityQuery) => $activityQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
            ]);

        $this->applySearchTerms($query, $searchTerms, function (
            Builder $innerQuery,
            string $likeTerm,
        ): void {
            $innerQuery
                ->where("name", "like", $likeTerm)
                ->orWhere("type", "like", $likeTerm)
                ->orWhere("status", "like", $likeTerm)
                ->orWhere("source", "like", $likeTerm)
                ->orWhere("outcome", "like", $likeTerm)
                ->orWhere("notes", "like", $likeTerm)
                ->orWhereHas("company", function (Builder $companyQuery) use (
                    $likeTerm,
                ): void {
                    $companyQuery->where("name", "like", $likeTerm);
                })
                ->orWhereHas("contact", function (Builder $contactQuery) use (
                    $likeTerm,
                ): void {
                    $contactQuery->where("name", "like", $likeTerm);
                })
                ->orWhereHas("activity", function (Builder $activityQuery) use (
                    $likeTerm,
                ): void {
                    $activityQuery->where("name", "like", $likeTerm);
                });
        });

        return $query
            ->orderByDesc("updated_at")
            ->orderByDesc("id")
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get()
            ->map(
                fn(Task $task): array => [
                    "title" => $task->name,
                    "subtitle" => $this->buildSubtitle(
                        [
                            Str::headline($task->type),
                            Str::headline($task->status),
                            $task->task_at?->format("M d, Y"),
                            $task->company?->name,
                            $task->contact?->name,
                        ],
                        __("Task record"),
                    ),
                    "url" => route("tasks.show", ["task" => $task]),
                ],
            )
            ->all();
    }

    /**
     * @param array<int, string> $searchTerms
     * @return array<int, array{title:string,subtitle:string,url:string}>
     */
    private function searchDeals(User $user, array $searchTerms): array
    {
        $query = Deal::query()
            ->where("user_id", $user->id)
            ->select([
                "id",
                "company_id",
                "contact_id",
                "activity_id",
                "name",
                "type",
                "status",
                "source",
                "amount",
                "currency",
                "expected_close_at",
                "updated_at",
            ])
            ->with([
                "company" => fn($companyQuery) => $companyQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
                "contact" => fn($contactQuery) => $contactQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
                "activity" => fn($activityQuery) => $activityQuery->select([
                    "id",
                    "name",
                    "user_id",
                ]),
            ]);

        $this->applySearchTerms($query, $searchTerms, function (
            Builder $innerQuery,
            string $likeTerm,
        ): void {
            $innerQuery
                ->where("name", "like", $likeTerm)
                ->orWhere("type", "like", $likeTerm)
                ->orWhere("status", "like", $likeTerm)
                ->orWhere("source", "like", $likeTerm)
                ->orWhere("currency", "like", $likeTerm)
                ->orWhere("outcome", "like", $likeTerm)
                ->orWhere("notes", "like", $likeTerm)
                ->orWhereHas("company", function (Builder $companyQuery) use (
                    $likeTerm,
                ): void {
                    $companyQuery->where("name", "like", $likeTerm);
                })
                ->orWhereHas("contact", function (Builder $contactQuery) use (
                    $likeTerm,
                ): void {
                    $contactQuery->where("name", "like", $likeTerm);
                })
                ->orWhereHas("activity", function (Builder $activityQuery) use (
                    $likeTerm,
                ): void {
                    $activityQuery->where("name", "like", $likeTerm);
                });
        });

        return $query
            ->orderByDesc("updated_at")
            ->orderByDesc("id")
            ->limit(self::MAX_RESULTS_PER_GROUP)
            ->get()
            ->map(
                fn(Deal $deal): array => [
                    "title" => $deal->name,
                    "subtitle" => $this->buildSubtitle(
                        [
                            Str::headline($deal->status),
                            $this->formatDealAmount($deal),
                            $deal->expected_close_at?->format("M d, Y"),
                            $deal->company?->name,
                            $deal->contact?->name,
                        ],
                        __("Deal record"),
                    ),
                    "url" => route("deals.show", ["deal" => $deal]),
                ],
            )
            ->all();
    }

    private function supportsFullTextSearch(): bool
    {
        return in_array(
            DB::connection()->getDriverName(),
            ["mysql", "mariadb"],
            true,
        );
    }

    /**
     * @param array<int, string> $searchTerms
     */
    private function buildFullTextBooleanQuery(array $searchTerms): ?string
    {
        $normalizedTerms = collect($searchTerms)
            ->map(function (string $term): string {
                $sanitized = preg_replace("/[^\\pL\\pN@._-]+/u", "", $term);

                return mb_strtolower((string) $sanitized);
            })
            ->filter(fn(string $term): bool => $term !== "")
            ->values();

        if ($normalizedTerms->isEmpty()) {
            return null;
        }

        if (
            $normalizedTerms->contains(
                fn(string $term): bool => mb_strlen($term) < 3,
            )
        ) {
            return null;
        }

        return $normalizedTerms
            ->map(fn(string $term): string => "+{$term}*")
            ->implode(" ");
    }

    /**
     * @param Builder<Company|Contact|Activity|Task|Deal> $query
     * @param array<int, string> $searchTerms
     * @param callable(Builder<Company|Contact|Activity|Task|Deal>, string): void $constraintBuilder
     */
    private function applySearchTerms(
        Builder $query,
        array $searchTerms,
        callable $constraintBuilder,
    ): void {
        foreach ($searchTerms as $term) {
            $escapedTerm = addcslashes($term, "%_\\");
            $likeTerm = "%{$escapedTerm}%";

            $query->where(function (Builder $innerQuery) use (
                $constraintBuilder,
                $likeTerm,
            ): void {
                $constraintBuilder($innerQuery, $likeTerm);
            });
        }
    }

    private function sanitizeQuery(string $query): string
    {
        return Str::of(strip_tags($query))
            ->squish()
            ->limit(self::MAX_QUERY_LENGTH, "")
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    private function extractSearchTerms(string $query): array
    {
        return collect(explode(" ", $query))
            ->map(fn(string $term): string => trim($term))
            ->filter()
            ->take(self::MAX_SEARCH_TERMS)
            ->values()
            ->all();
    }

    private function authenticatedUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param array<int, string|null> $parts
     */
    private function buildSubtitle(array $parts, string $fallback): string
    {
        $subtitle = collect($parts)
            ->filter(fn(?string $part): bool => filled($part))
            ->implode(" · ");

        return $subtitle !== "" ? $subtitle : $fallback;
    }

    private function buildLocation(?string $city, ?string $country): ?string
    {
        $location = collect([$city, $country])
            ->filter(fn(?string $value): bool => filled($value))
            ->implode(", ");

        return $location !== "" ? $location : null;
    }

    private function formatDealAmount(Deal $deal): string
    {
        return sprintf(
            "%s %s",
            $deal->currency,
            number_format((float) $deal->amount, 2),
        );
    }

    /**
     * @return array<string, array<int, array{title:string,subtitle:string,url:string}>>
     */
    private function emptyResultGroups(): array
    {
        return [
            "companies" => [],
            "contacts" => [],
            "activities" => [],
            "tasks" => [],
            "deals" => [],
        ];
    }
};
?>

<div>
    <flux:modal
        name="global-search-modal"
        class="w-full max-w-2xl"
        @close="$wire.clearSearch()"
    >
        <div class="space-y-5">
            {{-- Header --}}
            <flux:heading size="lg">{{ __('Global Search') }}</flux:heading>

            {{-- Search Input --}}
            <div class="space-y-3">
                <flux:input
                    wire:model.live.debounce.300ms="query"
                    type="search"
                    :placeholder="__('Search companies, contacts, activities, tasks, deals\u2026')"
                    aria-label="{{ __('Global search') }}"
                    autofocus
                />

                <div class="flex items-center justify-between gap-3 px-1">
                    <div class="flex items-center gap-2">
                        <div wire:loading class="flex items-center gap-1.5">
                            <svg class="size-3.5 animate-spin text-zinc-400 dark:text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.568 3 7.291l3-2.708z"></path>
                            </svg>
                            <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Searching\u2026') }}</flux:text>
                        </div>

                        <div wire:loading.remove>
                            @if ($this->canSearch && $this->hasResults)
                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ trans_choice(':count result found|:count results found', $this->totalResults) }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <kbd class="hidden select-none items-center rounded border border-zinc-200 bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-500 lg:inline-flex dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">⌘K</kbd>

                        @if ($query !== '')
                            <flux:button size="sm" variant="ghost" wire:click="clearSearch">
                                {{ __('Clear') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Results --}}
            <div class="max-h-[60vh] space-y-5 overflow-y-auto">
                @if (! $this->canSearch)
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-200 px-6 py-12 text-center dark:border-zinc-700">
                        <flux:icon.magnifying-glass class="size-8 text-zinc-300 dark:text-zinc-600" />
                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                            {{ __('Type at least 2 characters to start searching.') }}
                        </flux:text>
                    </div>
                @elseif (! $this->hasResults)
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-200 px-6 py-12 text-center dark:border-zinc-700">
                        <flux:icon.magnifying-glass class="size-8 text-zinc-300 dark:text-zinc-600" />
                        <div class="space-y-1">
                            <flux:text class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('No results found') }}</flux:text>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No records matched ":query".', ['query' => $this->normalizedQuery]) }}
                            </flux:text>
                        </div>
                    </div>
                @else
                    @foreach ($this->categoryConfig as $category => $config)
                        @php
                            $items = $results[$category] ?? [];
                        @endphp

                        @if ($items !== [])
                            <section class="space-y-2">
                                <div class="flex items-center justify-between px-1">
                                    <div class="flex items-center gap-2">
                                        <flux:icon :name="$config['icon']" variant="mini" class="size-4 text-zinc-400 dark:text-zinc-500" />
                                        <flux:heading size="sm">{{ $config['label'] }}</flux:heading>
                                    </div>
                                    <flux:badge size="sm" color="zinc">{{ count($items) }}</flux:badge>
                                </div>

                                <div class="space-y-1.5">
                                    @foreach ($items as $item)
                                        <a
                                            href="{{ $item['url'] }}"
                                            wire:navigate
                                            class="group flex items-center gap-4 rounded-lg border border-zinc-200 bg-white px-4 py-3.5 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:hover:bg-zinc-800"
                                        >
                                            <div class="min-w-0 flex-1 space-y-0.5">
                                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $item['title'] }}
                                                </p>
                                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $item['subtitle'] }}
                                                </p>
                                            </div>
                                            <flux:icon.arrow-right variant="micro" class="size-3.5 shrink-0 text-zinc-300 group-hover:text-zinc-500 dark:text-zinc-600 dark:group-hover:text-zinc-400" />
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    @endforeach
                @endif
            </div>

            {{-- Footer hint --}}
            <div class="flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
                    {{ __('Showing top :count per category', ['count' => 5]) }}
                </flux:text>
                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
                    {{ __('Press') }} <kbd class="select-none rounded border border-zinc-200 bg-zinc-100 px-1 py-0.5 font-mono text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">Esc</kbd> {{ __('to close') }}
                </flux:text>
            </div>
        </div>
    </flux:modal>
</div>
