<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class TaskController extends Controller
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
        'task_at',
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
        $this->authorize('viewAny', Task::class);

        /** @var User $user */
        $user = $request->user();

        $filters = $this->sanitizeIndexFilters($request);

        $searchTerms = collect(explode(' ', $filters['search']))
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $tasks = $user
            ->tasks()
            ->with([
                'company:id,name,user_id',
                'contact:id,name,user_id',
                'activity:id,name,user_id',
            ])
            ->select([
                'id',
                'company_id',
                'contact_id',
                'activity_id',
                'name',
                'type',
                'status',
                'source',
                'task_at',
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
            ->when($filters['activity'] !== 'all', function (
                Builder $query,
            ) use ($filters): void {
                $query->where('activity_id', (int) $filters['activity']);
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
                            )
                            ->orWhereHas(
                                'activity',
                                fn (
                                    Builder $activityQuery,
                                ): Builder => $activityQuery->where(
                                    'name',
                                    'like',
                                    $likeTerm,
                                ),
                            );
                    });
                }
            });

        if ($filters['sort'] === 'next_follow_up_at') {
            $tasks
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at', $filters['direction']);
        } else {
            $tasks->orderBy($filters['sort'], $filters['direction']);
        }

        $tasks = $tasks
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'search' => $filters['search'],
            'filters' => $filters,
            'statuses' => Task::statuses(),
            'types' => Task::types(),
            'sortOptions' => [
                'updated_at' => __('Recently updated'),
                'name' => __('Task title'),
                'type' => __('Type'),
                'status' => __('Status'),
                'task_at' => __('Task date'),
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
        $this->authorize('create', Task::class);

        return view('tasks.create', [
            'statuses' => Task::statuses(),
            'types' => Task::types(),
            'companies' => $this->companyOptionsForUser($request),
            'contacts' => $this->contactOptionsForUser($request),
            'activities' => $this->activityOptionsForUser($request),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $task = $user->tasks()->create($request->validated());

            return redirect()
                ->route('tasks.show', $task)
                ->with('status', __('Task created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'task' => __(
                        'We could not create this task right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): View
    {
        $task = $this->resolveTask($request, $id);

        $this->authorize('view', $task);

        return view('tasks.show', [
            'task' => $task,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id): View
    {
        $task = $this->resolveTask($request, $id);

        $this->authorize('update', $task);

        return view('tasks.edit', [
            'task' => $task,
            'statuses' => Task::statuses(),
            'types' => Task::types(),
            'companies' => $this->companyOptionsForUser($request),
            'contacts' => $this->contactOptionsForUser($request),
            'activities' => $this->activityOptionsForUser($request),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateTaskRequest $request,
        string $id,
    ): RedirectResponse {
        $task = $this->resolveTask($request, $id);

        $this->authorize('update', $task);

        try {
            $task->update($request->validated());

            return redirect()
                ->route('tasks.show', $task)
                ->with('status', __('Task updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'task' => __(
                        'We could not update this task right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $task = $this->resolveTask($request, $id);

        $this->authorize('delete', $task);

        try {
            $task->delete();

            return redirect()
                ->route('tasks.index')
                ->with('status', __('Task deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('tasks.index')
                ->withErrors([
                    'task' => __(
                        'We could not delete this task right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the task ensuring user-level data isolation.
     */
    private function resolveTask(Request $request, string $id): Task
    {
        /** @var User $user */
        $user = $request->user();

        return Task::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Normalize and whitelist index filters.
     *
     * @return array{search:string,status:string,type:string,active:string,follow_up:string,company:string,contact:string,activity:string,sort:string,direction:string,per_page:int}
     */
    private function sanitizeIndexFilters(Request $request): array
    {
        $search = Str::of(strip_tags((string) $request->query('search', '')))
            ->squish()
            ->limit(120, '')
            ->toString();

        $status = (string) $request->query('status', 'all');
        $allowedStatuses = array_merge(['all'], Task::statuses());

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $type = (string) $request->query('type', 'all');
        $allowedTypes = array_merge(['all'], Task::types());

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

        $activity = $this->sanitizeOwnedRelationFilter(
            value: (string) $request->query('activity', 'all'),
            user: $user,
            model: Activity::class,
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
            'activity' => $activity,
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
     * Get activity options for the authenticated user.
     *
     * @return Collection<int, Activity>
     */
    private function activityOptionsForUser(Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();

        return $user
            ->activities()
            ->select(['id', 'name', 'activity_at'])
            ->orderByDesc('activity_at')
            ->orderByDesc('id')
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
