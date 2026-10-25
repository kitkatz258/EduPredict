@php
    $role = auth()->user()?->role;
    $groups = match ($role) {
        \App\Enums\UserRole::DepartmentHead => [
            'General' => [
                ['department.dashboard', 'Dashboard', 'ri-dashboard-line'],
                ['department.students', 'Students', 'ri-team-line'],
            ],
        ],
        \App\Enums\UserRole::Dean => [
            'General' => [
                ['dean.dashboard', 'CLAS dashboard', 'ri-dashboard-line'],
            ],
        ],
        \App\Enums\UserRole::Administrator => [
            'General' => [
                ['admin.dashboard', 'Dashboard', 'ri-dashboard-line'],
                ['admin.users', 'Users', 'ri-group-line'],
                ['admin.institution-students', 'Eligible students', 'ri-file-list-3-line'],
                ['admin.colleges', 'Academic structure', 'ri-building-2-line'],
                ['admin.questionnaire', 'Questionnaire', 'ri-questionnaire-line'],
                ['admin.psoc', 'PSOC occupations', 'ri-briefcase-4-line'],
                ['admin.interventions', 'Interventions', 'ri-hand-heart-line'],
            ],
            'Oversight' => [
                ['admin.audit', 'Activity Log', 'ri-file-history-line'],
                ['admin.deletion-requests', 'Deletion requests', 'ri-user-unfollow-line'],
            ],
        ],
        default => [],
    };
@endphp

@foreach ($groups as $group => $links)
    <p class="sb-group-label px-2.5 pb-2 pt-[18px] text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5d6472]">{{ $group }}</p>
    @foreach ($links as [$route, $label, $icon])
        @php $isActive = request()->routeIs($route); @endphp
        <a
            href="{{ route($route) }}"
            title="{{ $label }}"
            class="sb-link {{ $isActive ? 'bg-white/10 font-medium text-white' : 'text-[#a3a9b5] hover:bg-white/[0.06] hover:text-white' }} relative mb-0.5 flex items-center gap-3 rounded-[10px] px-2.5 py-[9px] text-sm"
            @if ($isActive) aria-current="page" @endif
        >
            <i class="{{ $icon }} {{ $isActive ? 'text-[#818cf8]' : '' }} text-lg leading-none" aria-hidden="true"></i>
            <span class="sb-label truncate">{{ $label }}</span>
        </a>
    @endforeach
@endforeach
