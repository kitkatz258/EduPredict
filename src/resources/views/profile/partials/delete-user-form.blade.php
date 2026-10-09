<section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="text-lg font-semibold text-brand-900">Account deletion</h2>
    @if (auth()->user()->isRole(\App\Enums\UserRole::Student))
        <p class="mt-1 text-sm text-gray-600">Deletion is a reviewed request. Your login stays active until an administrator approves it, and prediction history is kept.</p>
        <a href="{{ route('privacy') }}#deletion-request" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-brand-900 hover:underline">
            Request account deletion<i class="ri-arrow-right-s-line" aria-hidden="true"></i>
        </a>
    @else
        <p class="mt-1 text-sm text-gray-600">Staff accounts are deactivated by an administrator. This page does not delete an account, and prediction history is kept.</p>
    @endif
</section>
