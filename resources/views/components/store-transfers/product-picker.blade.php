@props([
    'item',
    'label',
    'note',
])

@php($hiddenInputId = 'receiver-product-id-' . $item->id)

<div class="space-y-3">
    <label class="block font-bold ui-text-caption ui-text-soft" for="picker-input-{{ $item->id }}">{{ $label }}</label>
    <input type="hidden" id="{{ $hiddenInputId }}" name="receiver_product_id[{{ $item->id }}]">
    <div class="relative" data-transfer-product-picker data-hidden-input="{{ $hiddenInputId }}">
        <input type="text"
               id="picker-input-{{ $item->id }}"
               data-picker-input
               required
               autocomplete="off"
               placeholder="ابحث في جميع منتجات المتجر المستلم..."
               class="ui-input px-3 py-2 text-sm">
        <div data-picker-options class="absolute z-50 mt-2 hidden max-h-56 w-full overflow-y-auto rounded-xl border ui-border ui-surface-strong-bg shadow-2xl">
            @foreach(($item->receiverSuggestions ?? collect()) as $suggestion)
                <button type="button"
                        data-picker-option
                        data-id="{{ $suggestion->id }}"
                        data-label="{{ $suggestion->name }}"
                        data-search="{{ trim($suggestion->name.' '.($suggestion->barcode ?? '')) }}"
                        class="block w-full px-3 py-2 text-right text-sm ui-title ui-hover-surface">
                    {{ $suggestion->name }}
                </button>
            @endforeach
            <p data-picker-empty class="hidden p-3 text-center ui-text-caption ui-text-muted">لا يوجد منتج بهذا الاسم ضمن منتجات المتجر المستلم.</p>
        </div>
    </div>
    <p class="ui-text-caption ui-status-warning">{{ $note }}</p>
</div>
