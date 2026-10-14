@props(['label', 'value', 'icon' => 'chart', 'hint' => 'إجمالي السجلات'])
<article class="rounded-2xl border border-stone-200 bg-white p-5">
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-medium text-stone-600">{{ $label }}</h2><span
            class="rounded-lg bg-stone-50 p-2 text-emerald-800"><x-owner-icon :name="$icon" /></span>
    </div>
    <p class="mt-5 text-3xl font-bold tabular-nums">{{ $value === null ? 'غير متاح' : number_format($value) }}</p>
    <p class="mt-3 text-xs leading-6 text-stone-500">{{ $value === null ? 'مصدر المعلومات غير متاح حالياً.' : $hint }}
    </p>
</article>
