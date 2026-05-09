<x-layouts::app :title="__('Companies')">
    <div class="mx-auto flex h-full w-full max-w-[120rem] flex-1 flex-col gap-6 rounded-xl">
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

        @php
            $baseQuery = request()->only([
                'search',
                'status',
                'active',
                'follow_up',
                'sort',
                'direction',
                'per_page',
            ]);

            $withQuery = fn (array $changes): array => array_filter(
                array_merge($baseQuery, $changes),
                fn ($value): bool => $value !== '' && $value !== null,
            );

            $statusOptions = array_merge(
                ['all' => __('All')],
                collect($statuses)
                    ->mapWithKeys(fn (string $status): array => [$status => \Illuminate\Support\Str::headline($status)])
                    ->all(),
            );

            $activeOptions = [
                'all' => __('All'),
                'active' => __('Active'),
                'inactive' => __('Inactive'),
            ];

            $followUpOptions = [
                'all' => __('All'),
                'due' => __('Due'),
                'upcoming' => __('Upcoming'),
                'none' => __('No date'),
            ];

            $statusLabel = $statusOptions[$filters['status']] ?? __('All');
            $activeLabel = $activeOptions[$filters['active']] ?? __('All');
            $followUpLabel = $followUpOptions[$filters['follow_up']] ?? __('All');
            $sortLabel = $sortOptions[$filters['sort']] ?? __('Recently updated');
            $directionLabel = $filters['direction'] === 'asc' ? __('Ascending') : __('Descending');
        @endphp

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
                        :value="$search"
                        type="search"
                        :placeholder="__('Search by company, contact, industry, location, or status')"
                        aria-label="{{ __('Search companies') }}"
                    />
                </div>

                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>

                    @if ($search !== '')
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
                            {{ __('Status: :value', ['value' => $statusLabel]) }}
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
                            {{ __('Account: :value', ['value' => $activeLabel]) }}
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
                            {{ __('Follow-up: :value', ['value' => $followUpLabel]) }}
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
                            {{ __('Sort: :value', ['value' => $sortLabel]) }}
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
                            {{ __('Order: :value', ['value' => $directionLabel]) }}
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
                            @foreach ($perPageOptions as $perPageOption)
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
                    <flux:table.row>
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
                                    onsubmit="return confirm('{{ __('Delete this company? This action cannot be undone.') }}');"
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
</x-layouts::app>
