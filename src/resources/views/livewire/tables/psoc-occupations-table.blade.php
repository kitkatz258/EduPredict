<div>
    <div class="mb-4 w-full sm:max-w-sm">
        <label for="psoc-search-{{ $this->getId() }}" class="sr-only">Search occupations</label>
        <input id="psoc-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search code, title, or group…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
    </div>
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('psoc_code')">PSOC code</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('title')">Title</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900"><button type="button" wire:click="sortBy('major_group')">Major group</button></th>
                        <th class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->psoc_code }}</td>
                            <td class="px-4 py-3">{{ $row->title }}</td>
                            <td class="px-4 py-3">{{ $row->major_group }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.psoc', ['occupation' => $row->id]) }}" class="font-medium text-brand-900 underline">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
