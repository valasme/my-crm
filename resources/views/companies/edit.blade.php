<x-layouts::app :title="__('Edit :name', ['name' => $company->name])">
    <livewire:pages::companies.edit :company="$company->id" />
</x-layouts::app>
