@extends('layouts.guest')

@section('content')
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center gap-10 px-4 py-12 lg:flex-row lg:items-center">
        <div class="flex-1">
            <p class="text-sm font-medium text-brand-900">University of Caloocan City</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight text-brand-900">EduPredict</h1>
            <p class="mt-4 max-w-xl text-gray-600">
                Advisory estimates of employability and dropout risk for faculty and department review.
                Results never decide admission, academic standing, employment, or discipline.
            </p>
            <p class="mt-6 text-sm text-gray-500">
                <a href="{{ url('/demo/table') }}" class="font-medium text-brand-900 underline underline-offset-2">Open the M0 demo table</a>
            </p>
        </div>

        <div class="w-full max-w-md rounded-2xl border border-brand-200 bg-white p-8 shadow-sm">
            <h2 class="text-lg font-semibold text-brand-900">Sign in</h2>
            <p class="mt-1 text-sm text-gray-500">Email and password authentication is enabled in the next milestone.</p>
            <form class="mt-6 space-y-4" action="#" method="post" onsubmit="return false;">
                @csrf
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" name="email" type="email" autocomplete="username" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200" placeholder="you@example.com">
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm focus:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-200">
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-[#2E7D32]">
                    Sign in
                </button>
            </form>
        </div>
    </div>
@endsection
