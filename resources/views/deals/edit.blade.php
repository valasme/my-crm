<x-layouts::app :title="__('Edit :name', ['name' => $deal->name])">
    <livewire:pages::deals.edit :deal="$deal->id" />
</x-layouts::app>
