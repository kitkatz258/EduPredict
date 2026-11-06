<div>
    <div class="mb-4 flex flex-col gap-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="item-search-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Search</label>
                    <input id="item-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.400ms="search" placeholder="Search items…" class="ui-field">
                </div>
                <div>
                    <label for="item-section-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Section</label>
                    <select id="item-section-{{ $this->getId() }}" wire:model.live="sectionFilter" class="ui-field">
                        <option value="">All sections</option>
                        @foreach ($sections as $value => $definition)
                            <option value="{{ $value }}">{{ $definition['label'] ?? $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="item-active-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Active</label>
                    <select id="item-active-{{ $this->getId() }}" wire:model.live="activeFilter" class="ui-field">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label for="item-draft-{{ $this->getId() }}" class="mb-1 block text-sm font-medium">Draft</label>
                    <select id="item-draft-{{ $this->getId() }}" wire:model.live="draftFilter" class="ui-field">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
            </div>
            @if ($mutable)
                <button type="button" wire:click="addItem" class="ui-btn ui-btn-primary min-h-11 shrink-0">
                    <i class="ri-add-line" aria-hidden="true"></i>Add question
                </button>
            @endif
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">{{ $mutable ? 'Draft questions can be edited, reordered, or removed.' : 'This questionnaire is read-only. Create an editable copy to change questions.' }}</p>
            <div class="flex items-center gap-2">
                <label for="per-page-{{ $this->getId() }}" class="text-sm text-gray-600">Per page</label>
                <select id="per-page-{{ $this->getId() }}" wire:model.live="perPage" class="ui-field w-auto">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <x-updating />
    <div class="overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-brand-200 text-sm">
                <thead class="bg-brand-50">
                    <tr>
                        @foreach ($columns as $column)
                            @php
                                $centered = in_array($column['key'], ['sort_order', 'is_active', 'is_draft'], true);
                            @endphp
                            <th scope="col" class="px-4 py-3 font-semibold text-brand-900 {{ $centered ? 'text-center' : 'text-left' }}">
                                <button type="button" wire:click="sortBy('{{ $column['key'] }}')" class="inline-flex min-h-11 items-center gap-1">
                                    {{ $column['label'] }}
                                    @if ($sortField === $column['key'])
                                        <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th scope="col" class="min-w-[7.5rem] px-4 py-3 text-center font-semibold text-brand-900">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-200">
                    @forelse ($rows as $index => $row)
                        @php
                            $place = $positions[$row->id] ?? ['index' => 0, 'count' => 1];
                            $sectionLabel = $sections[$row->section]['label'] ?? $row->section;
                        @endphp
                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-brand-50' }}">
                            <td class="px-4 py-3">{{ $sectionLabel }}</td>
                            <td class="px-4 py-3 text-center font-mono text-xs">{{ $row->displayCode() ?: $row->sort_order }}</td>
                            <td class="px-4 py-3">{{ config('edupredict.questionnaire.constructs.'.$row->construct, $row->construct) }}</td>
                            <td class="px-4 py-3 text-left">{{ $row->text }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-status-pill :tone="$row->is_active ? 'success' : 'neutral'">{{ $row->is_active ? 'Active' : 'Inactive' }}</x-status-pill>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-status-pill :tone="$row->is_draft ? 'warning' : 'info'">{{ $row->is_draft ? 'Draft' : 'Published' }}</x-status-pill>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex min-w-[7.5rem] flex-wrap items-center justify-center gap-1.5">
                                    @if ($mutable)
                                        <button
                                            type="button"
                                            wire:click="moveUp({{ $row->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="moveUp({{ $row->id }}),moveDown({{ $row->id }})"
                                            @disabled($place['index'] === 0)
                                            class="ui-btn ui-btn-secondary min-h-11 px-3"
                                            aria-label="Move up in {{ $sectionLabel }}"
                                        >Up</button>
                                        <button
                                            type="button"
                                            wire:click="moveDown({{ $row->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="moveUp({{ $row->id }}),moveDown({{ $row->id }})"
                                            @disabled($place['index'] >= $place['count'] - 1)
                                            class="ui-btn ui-btn-secondary min-h-11 px-3"
                                            aria-label="Move down in {{ $sectionLabel }}"
                                        >Down</button>
                                        <x-table-action icon="ri-pencil-line" wire:click="editItem({{ $row->id }})" label="Edit question" />
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $row->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="removeItem({{ $row->id }})"
                                            data-confirm="This question is removed from the draft. Saved answers on published questionnaires are not changed."
                                            data-confirm-title="Remove this question?"
                                            data-confirm-button="Remove"
                                            class="ui-btn ui-btn-secondary min-h-11 px-3"
                                        >Remove</button>
                                        <x-table-action
                                            :icon="$row->is_active ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'"
                                            :tone="$row->is_active ? 'danger' : 'neutral'"
                                            wire:click="toggleActive({{ $row->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleActive({{ $row->id }})"
                                            :label="$row->is_active ? 'Deactivate' : 'Activate'"
                                            :data-confirm="$row->is_active ? 'Students will no longer see this item. Saved answers stay stored.' : null"
                                            :data-confirm-title="$row->is_active ? 'Deactivate this item?' : null"
                                            :data-confirm-button="$row->is_active ? 'Deactivate' : null"
                                        />
                                    @else
                                        <span class="text-xs text-gray-500">Read only</span>
                                    @endif
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
