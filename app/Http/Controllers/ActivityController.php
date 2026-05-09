<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ActivityController extends Controller
{
    /**
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [15, 25, 50];

    /**
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = [
        'updated_at',
        'name',
        'type',
        'status',
        'activity_at',
        'next_follow_up_at',
        'created_at',
    ];

    /**
     * @var array<int, string>
     */
    private const SORT_DIRECTIONS = ['asc', 'desc'];

    /**
     * @var array<int, string>
     */
    private const ACTIVE_FILTERS = ['all', 'active', 'inactive'];

    /**
     * @var array<int, string>
     */
    private const FOLLOW_UP_FILTERS = ['all', 'due', 'upcoming', 'none'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        /** @var User $user */
        $user = $request->user();

        $filters = $this->sanitizeIndexFilters($request);

        $searchTerms = collect(explode(' ', $filters['search']))
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $activities = $user
            ->activities()
            ->with(['company:id,name,user_id', 'contact:id,name,user_id'])
            ->select([
                'id',
                'company_id',
                'contact_id',
                'name',
                'type',
                'status',
                'source',
                'activity_at',
                'next_follow_up_at',
                'is_active',
                'updated_at',
            ])
            ->when($filters['status'] !== 'all', function (Builder $query) use (
                $filters,
            ): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['type'] !== 'all', function (Builder $query) use (
                $filters,
            ): void {
                $query->where('type', $filters['type']);
            })
            ->when($filters['active'] !== 'all', function (Builder $query) use (
                $filters,
            ): void {
                $query->where('is_active', $filters['active'] === 'active');
            })
            ->when($filters['company'] !== 'all', function (
                Builder $query,
            ) use ($filters): void {
                $query->where('company_id', (int) $filters['company']);
            })
            ->when($filters['contact'] !== 'all', function (
                Builder $query,
            ) use ($filters): void {
                $query->where('contact_id', (int) $filters['contact']);
            })
            ->when($filters['follow_up'] !== 'all', function (
                Builder $query,
            ) use ($filters): void {
                match ($filters['follow_up']) {
                    'due' => $query
                        ->whereNotNull('next_follow_up_at')
                        ->whereDate(
                            'next_follow_up_at',
                            '<=',
                            now()->toDateString(),
                        ),
                    'upcoming' => $query
                        ->whereNotNull('next_follow_up_at')
                        ->whereDate(
                            'next_follow_up_at',
                            '>',
                            now()->toDateString(),
                        ),
                    'none' => $query->whereNull('next_follow_up_at'),
                    default => null,
                };
            })
            ->when($searchTerms->isNotEmpty(), function (Builder $query) use (
                $searchTerms,
            ): void {
                foreach ($searchTerms as $term) {
                    $escapedTerm = addcslashes($term, '%_\\');
                    $likeTerm = "%{$escapedTerm}%";

                    $query->where(function (Builder $innerQuery) use (
                        $likeTerm,
                    ): void {
                        $innerQuery
                            ->where('name', 'like', $likeTerm)
                            ->orWhere('type', 'like', $likeTerm)
                            ->orWhere('status', 'like', $likeTerm)
                            ->orWhere('source', 'like', $likeTerm)
                            ->orWhere('outcome', 'like', $likeTerm)
                            ->orWhere('notes', 'like', $likeTerm)
                            ->orWhereHas(
                                'company',
                                fn (
                                    Builder $companyQuery,
                                ): Builder => $companyQuery->where(
                                    'name',
                                    'like',
                                    $likeTerm,
                                ),
                            )
                            ->orWhereHas(
                                'contact',
                                fn (
                                    Builder $contactQuery,
                                ): Builder => $contactQuery->where(
                                    'name',
                                    'like',
                                    $likeTerm,
                                ),
                            );
                    });
                }
            });

        if ($filters['sort'] === 'next_follow_up_at') {
            $activities
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at', $filters['direction']);
        } else {
            $activities->orderBy($filters['sort'], $filters['direction']);
        }

        $activities = $activities
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'search' => $filters['search'],
            'filters' => $filters,
            'statuses' => Activity::statuses(),
            'types' => Activity::types(),
            'sortOptions' => [
                'updated_at' => __('Recently updated'),
                'name' => __('Activity title'),
                'type' => __('Type'),
                'status' => __('Status'),
                'activity_at' => __('Activity date'),
                'next_follow_up_at' => __('Next follow-up'),
                'created_at' => __('Created date'),
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Activity::class);

        return view('activities.create', [
            'statuses' => Activity::statuses(),
            'types' => Activity::types(),
            'companies' => $this->companyOptionsForUser($request),
            'contacts' => $this->contactOptionsForUser($request),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreActivityRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $activity = $user->activities()->create($request->validated());

            return redirect()
                ->route('activities.show', $activity)
                ->with('status', __('Activity created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'activity' => __(
                        'We could not create this activity right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): View
    {
        $activity = $this->resolveActivity($request, $id);

        $this->authorize('view', $activity);

        return view('activities.show', [
            'activity' => $activity,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id): View
    {
        $activity = $this->resolveActivity($request, $id);

        $this->authorize('update', $activity);

        return view('activities.edit', [
            'activity' => $activity,
            'statuses' => Activity::statuses(),
            'types' => Activity::types(),
            'companies' => $this->companyOptionsForUser($request),
            'contacts' => $this->contactOptionsForUser($request),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateActivityRequest $request,
        string $id,
    ): RedirectResponse {
        $activity = $this->resolveActivity($request, $id);

        $this->authorize('update', $activity);

        try {
            $activity->update($request->validated());

            return redirect()
                ->route('activities.show', $activity)
                ->with('status', __('Activity updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'activity' => __(
                        'We could not update this activity right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $activity = $this->resolveActivity($request, $id);

        $this->authorize('delete', $activity);

        try {
            $activity->delete();

            return redirect()
                ->route('activities.index')
                ->with('status', __('Activity deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('activities.index')
                ->withErrors([
                    'activity' => __(
                        'We could not delete this activity right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the activity ensuring user-level data isolation.
     */
    private function resolveActivity(Request $request, string $id): Activity
    {
        /** @var User $user */
        $user = $request->user();

        return Activity::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Normalize and whitelist index filters.
     *
     * @return array{search:string,status:string,type:string,active:string,follow_up:string,company:string,contact:string,sort:string,direction:string,per_page:int}
     */
    private function sanitizeIndexFilters(Request $request): array
    {
        $search = Str::of(strip_tags((string) $request->query('search', '')))
            ->squish()
            ->limit(120, '')
            ->toString();

        $status = (string) $request->query('status', 'all');
        $allowedStatuses = array_merge(['all'], Activity::statuses());

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $type = (string) $request->query('type', 'all');
        $allowedTypes = array_merge(['all'], Activity::types());

        if (! in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $active = (string) $request->query('active', 'all');

        if (! in_array($active, self::ACTIVE_FILTERS, true)) {
            $active = 'all';
        }

        $followUp = (string) $request->query('follow_up', 'all');

        if (! in_array($followUp, self::FOLLOW_UP_FILTERS, true)) {
            $followUp = 'all';
        }

        /** @var User|null $user */
        $user = $request->user();

        $company = $this->sanitizeOwnedRelationFilter(
            value: (string) $request->query('company', 'all'),
            user: $user,
            model: Company::class,
        );

        $contact = $this->sanitizeOwnedRelationFilter(
            value: (string) $request->query('contact', 'all'),
            user: $user,
            model: Contact::class,
        );

        $sort = (string) $request->query('sort', 'updated_at');

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'updated_at';
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));

        if (! in_array($direction, self::SORT_DIRECTIONS, true)) {
            $direction = 'desc';
        }

        $perPage = (int) $request->query('per_page', 15);

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        return [
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'active' => $active,
            'follow_up' => $followUp,
            'company' => $company,
            'contact' => $contact,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get company options for the authenticated user.
     *
     * @return Collection<int, Company>
     */
    private function companyOptionsForUser(Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();

        return $user
            ->companies()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get contact options for the authenticated user.
     *
     * @return Collection<int, Contact>
     */
    private function contactOptionsForUser(Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();

        return $user
            ->contacts()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Validate relation-based filter values against ownership.
     */
    private function sanitizeOwnedRelationFilter(
        string $value,
        ?User $user,
        string $model,
    ): string {
        if ($value === 'all' || $user === null) {
            return 'all';
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            return 'all';
        }

        /** @var Builder $ownedModelQuery */
        $ownedModelQuery = call_user_func([$model, 'query']);

        $exists = $ownedModelQuery
            ->where('user_id', $user->id)
            ->whereKey((int) $id)
            ->exists();

        if (! $exists) {
            return 'all';
        }

        return (string) $id;
    }
}
