<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="xl" as="h1" id="dashboard-heading" aria-label="{{ __('Dashboard') }}">{{ __('Dashboard') }}</flux:heading>
    </div>
</x-layouts::app>
