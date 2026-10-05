@php
    $role = auth()->user()?->role;
    $nav = fn (string $route) => request()->routeIs($route)
        ? 'block rounded-lg bg-white/15 px-3 py-2 font-medium text-white'
        : 'block rounded-lg px-3 py-2 text-white/90 hover:bg-white/10';
@endphp

@guest
    <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">Sign in</a>
@else
    @if ($role === \App\Enums\UserRole::Student)
        <a href="{{ route('student.dashboard') }}" class="{{ $nav('student.dashboard') }}">Dashboard</a>
        <a href="{{ route('student.results') }}" class="{{ $nav('student.results') }}">Results</a>
        <a href="{{ route('student.profile') }}" class="{{ $nav('student.profile') }}">My Profile</a>
        <a href="{{ route('student.grades') }}" class="{{ $nav('student.grades') }}">My Grades</a>
        <a href="{{ route('student.questionnaire') }}" class="{{ $nav('student.questionnaire') }}">Questionnaire</a>
        <a href="{{ route('profile.edit') }}" class="{{ $nav('profile.edit') }}">Account</a>
    @elseif ($role === \App\Enums\UserRole::Faculty)
        <a href="{{ route('faculty.dashboard') }}" class="{{ $nav('faculty.dashboard') }}">Advisees</a>
    @elseif ($role === \App\Enums\UserRole::DepartmentHead)
        <a href="{{ route('department.dashboard') }}" class="{{ $nav('department.dashboard') }}">Program dashboard</a>
    @elseif ($role === \App\Enums\UserRole::Dean)
        <a href="{{ route('dean.dashboard') }}" class="{{ $nav('dean.dashboard') }}">College dashboard</a>
    @elseif ($role === \App\Enums\UserRole::Administrator)
        <a href="{{ route('admin.dashboard') }}" class="{{ $nav('admin.dashboard') }}">Institution dashboard</a>
        <a href="{{ route('admin.users') }}" class="{{ $nav('admin.users') }}">Users</a>
        <a href="{{ route('admin.institution-students') }}" class="{{ $nav('admin.institution-students') }}">Institution students</a>
        <a href="{{ route('admin.advisers') }}" class="{{ $nav('admin.advisers') }}">Adviser assignment</a>
        <a href="{{ route('admin.questionnaire') }}" class="{{ $nav('admin.questionnaire') }}">Questionnaire</a>
        <a href="{{ route('demo.table') }}" class="{{ $nav('demo.table') }}">Demo table</a>
    @endif
@endguest
