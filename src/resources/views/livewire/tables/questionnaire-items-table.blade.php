<div>
    <div class="mb-4 flex flex-col gap-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="item-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                    <input id="item-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search items…" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="item-section-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Section</label>
                    <select id="item-section-{{ $this->getId() }}" wire:model.live="sectionFilter" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                        <option value="">All sections</option>
                        @foreach ($sections as $value => $definition)
                            <option value="{{ $value }}">{{ $definition['label'] ?? $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="item-version-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Version</label>
                    <select id="item-version-{{ $this->getId() }}" wire:model.live="versionFilter" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                        <option value="">All versions</option>
                        @foreach ($versions as $value => $definition)
                            <option value="{{ $value }}">{{ $definition['label'] ?? $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-3">
                    <div>
                        <label for="item-active-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Active</label>
                        <select id="item-active-{{ $this->getId() }}" wire:model.live="activeFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label for="item-draft-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Draft</label>
                        <select id="item-draft-{{ $this->getId() }}" wire:model.live="draftFilter" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                            <option value="">All</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" wire:click="$dispatch('add-questionnaire-item')" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-brand-900 px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">
                <i class="ri-add-line" aria-hidden="true"></i>Add item
            </button>
        </div>
        <div class="flex items-center gap-2">
            <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
            <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="rounded-lg border border-brand-200 bg-white px-2 py-2 text-sm">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">
                                <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="inline-flex items-center gap-1">
                                    {{ $column['label'] }}
                                    @if ($sortField === $column['key'])
                                        <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $row->definition_version }}</td>
                            <td class="px-4 py-3">{{ config('edupredict.questionnaire.sections.'.$row->section.'.label', $row->section) }}</td>
                            <td class="px-4 py-3">{{ $row->sort_order }}</td>
                            <td class="px-4 py-3">{{ config('edupredict.questionnaire.constructs.'.$row->construct, $row->construct) }}</td>
                            <td class="px-4 py-3">{{ $row->text }}</td>
                            <td class="px-4 py-3">{{ $row->reverse_scored ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $row->is_active ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">{{ $row->is_draft ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-3">
                                    <button type="button" wire:click="editItem({{ $row->id }})" class="font-medium text-brand-900 underline">Edit</button>
                                    <button type="button" wire:click="toggleActive({{ $row->id }})" class="font-medium text-brand-900 underline">{{ $row->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    <button type="button" wire:click="toggleDraft({{ $row->id }})" class="font-medium text-brand-900 underline">{{ $row->is_draft ? 'Mark published' : 'Mark draft' }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}" class="px-4 py-12 text-center text-gray-500">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-brand-200 px-4 py-3">{{ $rows->links() }}</div>
    </div>
</div>
