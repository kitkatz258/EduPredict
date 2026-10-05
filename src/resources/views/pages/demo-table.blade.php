@extends('layouts.app')

@section('content')
    <div class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold text-brand-900">Shared table demo</h2>
            <p class="mt-1 text-sm text-gray-600">Search, sort, and paginate using the reusable Livewire table. This page is a development fixture for M0.</p>
        </div>
        <livewire:tables.demo-users-table />
    </div>
@endsection
