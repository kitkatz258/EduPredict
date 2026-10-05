@php
    $role = auth()->user()?->role;
@endphp

@guest
    <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Sign in</a>
@else
    @if ($role === \App\Enums\UserRole::Student)
        <a href="{{ route('student.dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Dashboard</a>
        <a href="{{ route('student.grades') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Grades</a>
        <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Profile</a>
    @elseif ($role === \App\Enums\UserRole::Faculty)
        <a href="{{ route('faculty.dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Advisees</a>
    @elseif ($role === \App\Enums\UserRole::DepartmentHead)
        <a href="{{ route('department.dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Program dashboard</a>
    @elseif ($role === \App\Enums\UserRole::Dean)
        <a href="{{ route('dean.dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">College dashboard</a>
    @elseif ($role === \App\Enums\UserRole::Administrator)
        <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Institution dashboard</a>
        <a href="{{ route('admin.users') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Users</a>
        <a href="{{ route('admin.institution-students') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Institution students</a>
        <a href="{{ route('admin.advisers') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Adviser assignment</a>
        <a href="{{ route('demo.table') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Demo table</a>
    @endif
@endguest
