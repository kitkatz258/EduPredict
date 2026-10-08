@php
    $role = auth()->user()?->role;
    $links = match ($role) {
        \App\Enums\UserRole::DepartmentHead => [
            ['department.dashboard', 'Department dashboard', 'ri-dashboard-line'],
        ],
        \App\Enums\UserRole::Dean => [
            ['dean.dashboard', 'College dashboard', 'ri-dashboard-line'],
        ],
        \App\Enums\UserRole::Administrator => [
            ['admin.dashboard', 'Dashboard', 'ri-dashboard-line'],
            ['admin.users', 'Users', 'ri-group-line'],
            ['admin.institution-students', 'Institution students', 'ri-file-list-3-line'],
            ['admin.colleges', 'Academic structure', 'ri-building-2-line'],
            ['admin.questionnaire', 'Questionnaire', 'ri-questionnaire-line'],
            ['admin.psoc', 'PSOC occupations', 'ri-briefcase-4-line'],
            ['admin.interventions', 'Interventions', 'ri-hand-heart-line'],
            ['admin.audit', 'Audit log', 'ri-file-history-line'],
            ['admin.deletion-requests', 'Deletion requests', 'ri-user-unfollow-line'],
        ],
        default => [],
    };
@endphp

@foreach ($links as [$route, $label, $icon])
    @php $isActive = request()->routeIs($route); @endphp
    <a
        href="{{ route($route) }}"
        class="{{ $isActive ? 'bg-white/15 font-medium text-white' : 'text-white/85 hover:bg-white/10' }} flex items-center gap-3 rounded-lg px-3 py-2"
        @if ($isActive) aria-current="page" @endif
    >
        <i class="{{ $icon }} text-lg" aria-hidden="true"></i>{{ $label }}
    </a>
@endforeach
