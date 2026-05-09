<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
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

class ContactController extends Controller
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
        $this->authorize('viewAny', Contact::class);

        /** @var User $user */
        $user = $request->user();

        $filters = $this->sanitizeIndexFilters($request);

        $searchTerms = collect(explode(' ', $filters['search']))
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        $contacts = $user
            ->contacts()
            ->with('company:id,name,user_id')
            ->select([
                'id',
                'company_id',
                'name',
                'job_title',
                'status',
                'is_active',
                'email',
                'phone',
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
                            ->orWhere('job_title', 'like', $likeTerm)
                            ->orWhere('department', 'like', $likeTerm)
                            ->orWhere('source', 'like', $likeTerm)
                            ->orWhere('email', 'like', $likeTerm)
                            ->orWhere('alternate_email', 'like', $likeTerm)
                            ->orWhere('phone', 'like', $likeTerm)
                            ->orWhere('mobile_phone', 'like', $likeTerm)
                            ->orWhere('status', 'like', $likeTerm)
                            ->orWhere('city', 'like', $likeTerm)
                            ->orWhere('country', 'like', $likeTerm)
                            ->orWhereHas(
                                'company',
                                fn (
                                    Builder $companyQuery,
                                ): Builder => $companyQuery->where(
                                    'name',
                                    'like',
                                    $likeTerm,
                                ),
                            );
                    });
                }
            });

        if ($filters['sort'] === 'next_follow_up_at') {
            $contacts
                ->orderByRaw('next_follow_up_at is null')
                ->orderBy('next_follow_up_at', $filters['direction']);
        } else {
            $contacts->orderBy($filters['sort'], $filters['direction']);
        }

        $contacts = $contacts
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'search' => $filters['search'],
            'filters' => $filters,
            'statuses' => Contact::statuses(),
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
    public function create(Request $request): View
    {
        $this->authorize('create', Contact::class);

        return view('contacts.create', [
            'statuses' => Contact::statuses(),
            'preferredContactMethods' => Contact::preferredContactMethods(),
            'companies' => $this->companyOptionsForUser($request),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $contact = $user->contacts()->create($request->validated());

            return redirect()
                ->route('contacts.show', $contact)
                ->with('status', __('Contact created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'contact' => __(
                        'We could not create this contact right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): View
    {
        $contact = $this->resolveContact($request, $id);

        $this->authorize('view', $contact);

        return view('contacts.show', [
            'contact' => $contact,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id): View
    {
        $contact = $this->resolveContact($request, $id);

        $this->authorize('update', $contact);

        return view('contacts.edit', [
            'contact' => $contact,
            'statuses' => Contact::statuses(),
            'preferredContactMethods' => Contact::preferredContactMethods(),
            'companies' => $this->companyOptionsForUser($request),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateContactRequest $request,
        string $id,
    ): RedirectResponse {
        $contact = $this->resolveContact($request, $id);

        $this->authorize('update', $contact);

        try {
            $contact->update($request->validated());

            return redirect()
                ->route('contacts.show', $contact)
                ->with('status', __('Contact updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'contact' => __(
                        'We could not update this contact right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $contact = $this->resolveContact($request, $id);

        $this->authorize('delete', $contact);

        try {
            $contact->delete();

            return redirect()
                ->route('contacts.index')
                ->with('status', __('Contact deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('contacts.index')
                ->withErrors([
                    'contact' => __(
                        'We could not delete this contact right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the contact ensuring user-level data isolation.
     */
    private function resolveContact(Request $request, string $id): Contact
    {
        /** @var User $user */
        $user = $request->user();

        return Contact::query()
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
        $allowedStatuses = array_merge(['all'], Contact::statuses());

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
}
