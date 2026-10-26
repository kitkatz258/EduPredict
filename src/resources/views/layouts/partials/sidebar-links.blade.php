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
                ['admin.questionnaire', 'Questionnaire', 'ri-questionnaire-line'],
                ['admin.psoc', 'PSOC occupations', 'ri-briefcase-4-line'],
                ['admin.interventions', 'Interventions', 'ri-hand-heart-line'],
            ],
            'Oversight' => [
                ['admin.audit', 'Activity Log', 'ri-file-history-line'],
                ['admin.prediction-grants', 'Prediction grants', 'ri-calendar-check-line'],
                ['admin.deletion-requests', 'Deletion requests', 'ri-user-unfollow-line'],
            ],
        ],
        default => [],
    };

    $academicChildren = [
        ['admin.colleges', 'Colleges', 'ri-building-line'],
        ['admin.departments', 'Departments', 'ri-organization-chart'],
        ['admin.programs', 'Programs', 'ri-graduation-cap-line'],
    ];
    $isAdmin = $role === \App\Enums\UserRole::Administrator;
    $academicActive = request()->routeIs('admin.colleges', 'admin.departments', 'admin.programs');
@endphp

@foreach ($groups as $group => $links)
    <p class="sb-group-label px-2.5 pb-2 pt-[18px] text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5d6472]">{{ $group }}</p>
    @foreach ($links as [$route, $label, $icon])
        @if ($isAdmin && $group === 'General' && $route === 'admin.institution-students')
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
            <div class="mb-0.5">
                <button
                    type="button"
                    class="sb-link {{ $academicActive ? 'bg-white/10 font-medium text-white' : 'text-[#a3a9b5] hover:bg-white/[0.06] hover:text-white' }} relative flex w-full items-center gap-3 rounded-[10px] px-2.5 py-[9px] text-left text-sm"
                    @click="academicOpen = ! academicOpen"
                    :aria-expanded="academicOpen.toString()"
                    aria-controls="academic-structure-nav"
                    title="Academic structure"
                >
                    <i class="ri-building-2-line {{ $academicActive ? 'text-[#818cf8]' : '' }} text-lg leading-none" aria-hidden="true"></i>
                    <span class="sb-label min-w-0 flex-1 truncate">Academic structure</span>
                    <i class="sb-label ri-arrow-down-s-line text-base leading-none transition-transform" :class="academicOpen && 'rotate-180'" aria-hidden="true"></i>
                </button>
                <div
                    id="academic-structure-nav"
                    x-show="academicOpen"
                    x-cloak
                    class="sb-academic-children mt-0.5 space-y-0.5 lg:pl-4"
                    role="group"
                    aria-label="Academic structure pages"
                >
                    @foreach ($academicChildren as [$routeName, $childLabel, $childIcon])
                        @php $childActive = request()->routeIs($routeName); @endphp
                        <a
                            href="{{ route($routeName) }}"
                            title="{{ $childLabel }}"
                            class="sb-link {{ $childActive ? 'bg-white/10 font-medium text-white' : 'text-[#a3a9b5] hover:bg-white/[0.06] hover:text-white' }} relative flex items-center gap-3 rounded-[10px] px-2.5 py-[8px] text-sm"
                            @if ($childActive) aria-current="page" @endif
                        >
                            <i class="{{ $childIcon }} {{ $childActive ? 'text-[#818cf8]' : '' }} text-lg leading-none" aria-hidden="true"></i>
                            <span class="sb-label truncate">{{ $childLabel }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
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
        @endif
    @endforeach
@endforeach
