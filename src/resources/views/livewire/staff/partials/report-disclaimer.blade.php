@php
    $generatedLabel = $generatedAt->timezone(config('app.timezone'))->format('M j, Y g:i A');
@endphp

<details class="rounded-[11px] border border-[var(--ed-line)] bg-[var(--ed-surface)] px-4 py-3 text-sm print:hidden">
    <summary class="cursor-pointer font-medium text-[var(--ed-ink)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ed-accent)]">
        About these estimates
    </summary>
    <div class="mt-2 space-y-1 text-xs leading-5 text-[var(--ed-muted)]">
        <p>These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
        <x-model-disclosure />
        <p>Program-shift figures are counts of students. The indicator is qualitative and has no percentage.</p>
        <p>Generated {{ $generatedLabel }} Asia/Manila. Totals use each student's latest estimate unless a date filter is set.</p>
    </div>
</details>

<div class="hidden space-y-1 text-xs text-gray-600 print:block">
    <p>Generated {{ $generatedLabel }} Asia/Manila. Totals use each student's latest estimate unless a date filter is set.</p>
    <x-model-disclosure />
    <p>Program-shift figures are counts of students. The indicator is qualitative and has no percentage.</p>
    <p>These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
</div>
