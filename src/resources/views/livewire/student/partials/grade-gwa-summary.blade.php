<div class="mt-4 rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-3 text-sm">
    <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-gray-700">
        <span>Term GWA: <span class="font-semibold text-brand-900">{{ $gwa['rounded'] !== null ? number_format($gwa['rounded'], 2) : '—' }}</span></span>
        @if ($gwa['provisional'])
            <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900"><i class="ri-time-line" aria-hidden="true"></i>Provisional</span>
        @endif
        @if ($detected_gpa)
            <span>GPA shown on source: {{ $detected_gpa }}</span>
        @endif
        <span>Failed: {{ $gwa['failed'] }}</span>
        <span>Incomplete: {{ $gwa['incomplete'] }}</span>
    </p>
    @if ($gwa['provisional'])
        <p class="mt-2 text-xs text-amber-900">INC subjects have no numeric grade yet. They are left out of the GWA and are not counted as failed, so this GWA is provisional until you update the term with the final grade.</p>
    @endif
    @if ($gwa['mismatch'])
        <p class="mt-2 text-xs text-amber-900" role="status">
            The computed GWA differs from the GPA on the source by more than 0.01.
            @if ($gwa['provisional'])
                The portal may count INC differently; EduPredict leaves INC out.
            @endif
            Also check the highlighted rows for reading errors. NSTP subjects are excluded.
        </p>
    @endif
</div>
