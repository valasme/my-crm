<x-layouts::app :title="$company->name">
    @php
        $historyFallback = route('companies.index');
    @endphp

    <div class="mx-auto flex h-full w-full max-w-[120rem] flex-1 flex-col gap-6 rounded-xl">
        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <flux:heading size="xl" as="h1" id="show-company-heading" aria-label="{{ __('Show Company') }}">
                        {{ $company->name }}
                    </flux:heading>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge variant="solid">
                            {{ \Illuminate\Support\Str::headline($company->status) }}
                        </flux:badge>

                        <flux:badge>{{ $company->is_active ? __('Active') : __('Inactive') }}</flux:badge>

                        @if ($company->industry)
                            <flux:badge>{{ $company->industry }}</flux:badge>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button variant="primary" :href="route('companies.edit', $company)" wire:navigate>
                        {{ __('Edit') }}
                    </flux:button>

                    <form
                        method="POST"
                        action="{{ route('companies.destroy', $company) }}"
                        onsubmit="return confirm('{{ __('Delete this company? This action cannot be undone.') }}');"
                        class="inline-flex"
                    >
                        @csrf
                        @method('DELETE')
                        <flux:button variant="ghost" type="submit" aria-label="{{ __('Delete :company', ['company' => $company->name]) }}">
                            <flux:icon.trash variant="micro" />
                        </flux:button>
                    </form>

                    <flux:button variant="ghost" :href="route('companies.index')" wire:navigate>
                        {{ __('Companies') }}
                    </flux:button>
                </div>
            </div>

            <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:button
                    type="button"
                    variant="ghost"
                    onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href='{{ $historyFallback }}'; }"
                >
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

        @if ($errors->has('company'))
            <div role="alert" aria-live="assertive" aria-atomic="true">
                <flux:badge variant="solid">{{ $errors->first('company') }}</flux:badge>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card>
                <flux:heading>{{ __('Company Information') }}</flux:heading>

                <div class="mt-4 grid gap-3 text-sm">
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Legal name') }}</flux:text>
                        <flux:text>{{ $company->legal_name ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Lead source') }}</flux:text>
                        <flux:text>{{ $company->source ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Ownership type') }}</flux:text>
                        <flux:text>{{ $company->ownership_type ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Founded year') }}</flux:text>
                        <flux:text>{{ $company->founded_year ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Employees') }}</flux:text>
                        <flux:text>{{ $company->employee_count ? number_format($company->employee_count) : '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Annual revenue') }}</flux:text>
                        <flux:text>{{ $company->annual_revenue ? '$' . number_format((float) $company->annual_revenue, 2) : '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Tax ID') }}</flux:text>
                        <flux:text>{{ $company->tax_id ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Last contacted') }}</flux:text>
                        <flux:text>{{ $company->last_contacted_at?->format('M d, Y') ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Next follow-up') }}</flux:text>
                        <flux:text>{{ $company->next_follow_up_at?->format('M d, Y') ?: '—' }}</flux:text>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading>{{ __('Contact Information') }}</flux:heading>

                <div class="mt-4 grid gap-3 text-sm">
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Company email') }}</flux:text>
                        <flux:text>{{ $company->email ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Billing email') }}</flux:text>
                        <flux:text>{{ $company->billing_email ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Company phone') }}</flux:text>
                        <flux:text>{{ $company->phone ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Support phone') }}</flux:text>
                        <flux:text>{{ $company->support_phone ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Preferred contact') }}</flux:text>
                        <flux:text>{{ $company->preferred_contact_method ? \Illuminate\Support\Str::headline($company->preferred_contact_method) : '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Timezone') }}</flux:text>
                        <flux:text>{{ $company->timezone ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Website') }}</flux:text>
                        <flux:text>{{ $company->website ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('LinkedIn') }}</flux:text>
                        <flux:text>{{ $company->linkedin_url ?: '—' }}</flux:text>
                    </div>

                    <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="lg">{{ __('Primary Contact') }}</flux:heading>
                    </div>

                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Name') }}</flux:text>
                        <flux:text>{{ $company->primary_contact_name ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Email') }}</flux:text>
                        <flux:text>{{ $company->primary_contact_email ?: '—' }}</flux:text>
                    </div>
                    <div class="grid gap-1 sm:grid-cols-[160px_1fr] sm:gap-3">
                        <flux:text>{{ __('Phone') }}</flux:text>
                        <flux:text>{{ $company->primary_contact_phone ?: '—' }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading>{{ __('Address') }}</flux:heading>

            <div class="mt-4 grid gap-2 text-sm">
                <flux:text>{{ $company->address_line_1 ?: '—' }}</flux:text>
                @if ($company->address_line_2)
                    <flux:text>{{ $company->address_line_2 }}</flux:text>
                @endif
                <flux:text>
                    {{ collect([$company->city, $company->state, $company->postal_code])->filter()->implode(', ') ?: '—' }}
                </flux:text>
                <flux:text>{{ $company->country ?: '—' }}</flux:text>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading>{{ __('Notes') }}</flux:heading>
            <flux:text class="mt-4 whitespace-pre-line">{{ $company->notes ?: __('No notes yet.') }}</flux:text>
        </flux:card>
    </div>
</x-layouts::app>
