<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CompanyController extends Controller
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
        'status',
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
        $this->authorize('viewAny', Company::class);

        /** @var User $user */
        $user = $request->user();

        $filters = $this->sanitizeIndexFilters($request);

        $searchTerms = collect(explode(' ', $filters['search']))
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $companies = $user
            ->companies()
            ->with([
                'primaryContact' => fn ($contactQuery) => $contactQuery
                    ->select(['id', 'name', 'email', 'user_id'])
                    ->where('user_id', $user->id),
            ])
            ->select([
                'id',
                'primary_contact_id',
                'name',
                'industry',
                'status',
                'is_active',
                'preferred_contact_method',
                'next_follow_up_at',
                'updated_at',
            ])
            ->when($filters['status'] !== 'all', function (Builder $query) use (
                $filters,
            ): void {
                $query->where('status', $filters['status']);
            })
            ->when($filters['active'] !== 'all', function (Builder $query) use (
                $filters,
            ): void {
                $query->where('is_active', $filters['active'] === 'active');
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
                            ->orWhere('legal_name', 'like', $likeTerm)
                            ->orWhere('industry', 'like', $likeTerm)
                            ->orWhere('source', 'like', $likeTerm)
                            ->orWhereHas(
                                'primaryContact',
                                fn (
                                    Builder $contactQuery,
                                ): Builder => $contactQuery
                                    ->where('user_id', $user->id)
                                    ->where(function (
                                        Builder $scopedContactQuery,
                                    ) use ($likeTerm): void {
                                        $scopedContactQuery
                                            ->where('name', 'like', $likeTerm)
                                            ->orWhere(
                                                'email',
                                                'like',
                                                $likeTerm,
                                            );
                                    }),
                            )
                            ->orWhere('email', 'like', $likeTerm)
                            ->orWhere('phone', 'like', $likeTerm)
                            ->orWhere('status', 'like', $likeTerm)
                            ->orWhere('city', 'like', $likeTerm)
                            ->orWhere('country', 'like', $likeTerm);
                    });
                }
            });

        if ($filters['sort'] === 'next_follow_up_at') {
            $companies
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at', $filters['direction']);
        } else {
            $companies->orderBy($filters['sort'], $filters['direction']);
        }

        $companies = $companies
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('companies.index', [
            'companies' => $companies,
            'search' => $filters['search'],
            'filters' => $filters,
            'statuses' => Company::statuses(),
            'sortOptions' => [
                'updated_at' => __('Recently updated'),
                'name' => __('Name'),
                'status' => __('Status'),
                'next_follow_up_at' => __('Next follow-up'),
                'created_at' => __('Created date'),
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('companies.create', [
            'statuses' => Company::statuses(),
            'preferredContactMethods' => Company::preferredContactMethods(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $company = $user->companies()->create($request->validated());

            return redirect()
                ->route('companies.show', $company)
                ->with('status', __('Company created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'company' => __(
                        'We could not create this company right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): View
    {
        $company = $this->resolveCompany($request, $id);

        $this->authorize('view', $company);

        return view('companies.show', [
            'company' => $company,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id): View
    {
        $company = $this->resolveCompany($request, $id);

        $this->authorize('update', $company);

        return view('companies.edit', [
            'company' => $company,
            'statuses' => Company::statuses(),
            'preferredContactMethods' => Company::preferredContactMethods(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateCompanyRequest $request,
        string $id,
    ): RedirectResponse {
        $company = $this->resolveCompany($request, $id);

        $this->authorize('update', $company);

        try {
            $company->update($request->validated());

            return redirect()
                ->route('companies.show', $company)
                ->with('status', __('Company updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'company' => __(
                        'We could not update this company right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->resolveCompany($request, $id);

        $this->authorize('delete', $company);

        try {
            $company->delete();

            return redirect()
                ->route('companies.index')
                ->with('status', __('Company deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('companies.index')
                ->withErrors([
                    'company' => __(
                        'We could not delete this company right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the company ensuring user-level data isolation.
     */
    private function resolveCompany(Request $request, string $id): Company
    {
        /** @var User $user */
        $user = $request->user();

        return Company::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Normalize and whitelist index filters.
     *
     * @return array{search:string,status:string,active:string,follow_up:string,sort:string,direction:string,per_page:int}
     */
    private function sanitizeIndexFilters(Request $request): array
    {
        $search = Str::of(strip_tags((string) $request->query('search', '')))
            ->squish()
            ->limit(120, '')
            ->toString();

        $status = (string) $request->query('status', 'all');
        $allowedStatuses = array_merge(['all'], Company::statuses());

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $active = (string) $request->query('active', 'all');

        if (! in_array($active, self::ACTIVE_FILTERS, true)) {
            $active = 'all';
        }

        $followUp = (string) $request->query('follow_up', 'all');

        if (! in_array($followUp, self::FOLLOW_UP_FILTERS, true)) {
            $followUp = 'all';
        }

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
            'active' => $active,
            'follow_up' => $followUp,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ];
    }
}
