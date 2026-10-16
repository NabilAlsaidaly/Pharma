<fieldset class="min-w-0 rounded-xl border p-4" data-gift-row>
    <legend class="px-2 font-semibold">{{ $component['type'] === 'catalog' ? 'منتج من الكتالوج' : 'عنصر يدوي' }}</legend>
    <input type="hidden" name="components[{{ $index }}][type]" value="{{ $component['type'] }}">
    <div class="grid gap-3 sm:grid-cols-2">
        @if ($component['type'] === 'catalog')
            <label class="min-w-0 sm:col-span-2">المنتج والاختيار الدقيق
                <select required class="mt-2 block w-full min-w-0 rounded-xl border p-3"
                    name="components[{{ $index }}][selection]">
                    <option value="">اختاري منتجاً أو Variant محدداً</option>
                    @foreach ($choices as $key => $label)
                        <option value="{{ $key }}" @selected($key === ($component['product_id'] ?? '') . '|' . ($component['variant_id'] ?? ''))>{{ $label }}</option>
                    @endforeach
                </select></label>
        @else
            <label>اسم العنصر<input required maxlength="160" class="mt-2 block w-full rounded-xl border p-3"
                    name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}"></label>
            <label>وصف اختياري<input maxlength="1000" class="mt-2 block w-full rounded-xl border p-3"
                    name="components[{{ $index }}][description]"
                    value="{{ $component['description'] ?? '' }}"></label>
        @endif
        <label>الكمية (1–100)<input required type="number" min="1" max="100"
                class="mt-2 block w-full rounded-xl border p-3" name="components[{{ $index }}][quantity]"
                value="{{ $component['quantity'] ?? 1 }}"></label>
        <label>ترتيب العرض<input required type="number" min="0" max="999999999"
                class="mt-2 block w-full rounded-xl border p-3" name="components[{{ $index }}][sort_order]"
                value="{{ $component['sort_order'] ?? 0 }}"></label>
    </div><button type="button" class="mt-3 rounded-lg border px-4 py-2" data-gift-remove>إزالة المكون من
        المسودة</button>
</fieldset>
