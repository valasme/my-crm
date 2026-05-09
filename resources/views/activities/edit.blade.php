<x-layouts::app :title="__('Edit :name', ['name' => $activity->name])">
    <livewire:pages::activities.edit :activity="$activity->id" />
</x-layouts::app>
