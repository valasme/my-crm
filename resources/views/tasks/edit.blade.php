<x-layouts::app :title="__('Edit :name', ['name' => $task->name])">
    <livewire:pages::tasks.edit :task="$task->id" />
</x-layouts::app>
