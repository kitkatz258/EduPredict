@props([
    'submit' => 'Save',
    'form' => null,
    'cancel' => 'Cancel',
    'readOnly' => false,
    'target' => 'save,confirmSave',
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap justify-end gap-2']) }}>
    <button type="button" x-on:click="requestClose()" class="ui-btn ui-btn-secondary">
        {{ $readOnly ? 'Close' : $cancel }}
    </button>
    @unless ($readOnly)
        <button
            type="submit"
            @if ($form) form="{{ $form }}" @endif
            wire:loading.attr="disabled"
            wire:target="{{ $target }}"
            class="ui-btn ui-btn-primary"
        >
            <x-spinner wire:loading wire:target="{{ $target }}" />
            {{ $submit }}
        </button>
    @endunless
</div>
