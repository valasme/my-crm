@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="MyCRM" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-5 fill-current dark:invert invert-0" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="MyCRM" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-5 fill-current dark:invert invert-0" />
        </x-slot>
    </flux:brand>
@endif
