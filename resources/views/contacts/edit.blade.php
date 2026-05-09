<x-layouts::app :title="__('Edit :name', ['name' => $contact->name])">
    <livewire:pages::contacts.edit :contact="$contact->id" />
</x-layouts::app>
