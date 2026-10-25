<x-app-layout>
    <div class="space-y-6">
        <x-page-header
            title="Users"
            description="Staff and student accounts that can sign in. Search, filters, and row actions stay on this list."
        />
        <livewire:admin.create-staff-form />
        <livewire:admin.edit-user-form />
        <livewire:tables.users-table />
    </div>
</x-app-layout>
