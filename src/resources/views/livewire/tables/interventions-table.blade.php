<div>
    <div class="mb-4 w-full sm:max-w-sm">
        <label for="intervention-search-{{ $this->getId() }}" class="sr-only">Search interventions</label>
        <input id="intervention-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search interventions…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
    </div>
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('code')">Code</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('title')">Intervention</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('min_risk_level')">Minimum risk</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Factors</th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->code }}</td>
                            <td class="px-4 py-3">{{ $row->title }}</td>
                            <td class="px-4 py-3">{{ ucfirst($row->min_risk_level) }}</td>
                            <td class="px-4 py-3">{{ \App\Support\CommaList::display($row->targets_factor) }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.interventions', ['intervention' => $row->id]) }}" class="font-medium text-brand-900 underline">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
