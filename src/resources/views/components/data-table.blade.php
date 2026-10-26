<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[14px] border border-[#e8eaee] bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04),0_1px_3px_rgba(16,24,40,0.03)]']) }}>
    @isset($toolbar)
        <div class="border-b border-[#e8eaee] px-4 py-4 sm:px-5">
            {{ $toolbar }}
        </div>
    @endisset
    <div class="overflow-x-auto px-3 pt-3 sm:px-4">
        <table class="ui-table min-w-[720px] text-sm">
            {{ $slot }}
        </table>
    </div>
    @isset($footer)
        <div class="border-t border-[#e8eaee] px-4 py-3 sm:px-5">
            {{ $footer }}
        </div>
    @endisset
</div>
