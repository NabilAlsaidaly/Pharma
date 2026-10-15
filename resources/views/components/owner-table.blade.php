@props(['title', 'empty' => false])
<section class="min-w-0 rounded-2xl border border-stone-200 bg-white" aria-label="{{ $title }}" data-selection>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 px-5 py-4">
        <h2 class="text-sm font-semibold">{{ $title }}</h2>
        <div class="flex items-center gap-3 text-xs"><span data-selected-count role="status" aria-live="polite">لم يتم
                تحديد سجلات</span><button type="button" data-clear-selection disabled
                class="rounded-lg border border-stone-200 px-3 py-2 disabled:opacity-50">إلغاء التحديد</button></div>
    </div>
    @if ($empty)
    <p class="p-8 text-center text-sm leading-7 text-stone-500">لا توجد سجلات تطابق البحث والمرشحات الحالية.</p>@else
        <div class="overflow-x-auto" tabindex="0" role="region"
            aria-label="جدول {{ $title }}، يمكن تمريره أفقياً">
            <table class="w-full min-w-[40rem] text-right text-sm">
                <caption class="sr-only">{{ $title }}</caption>
                <thead class="bg-stone-50 text-xs text-stone-600">{{ $head }}</thead>
                <tbody class="divide-y divide-stone-100">{{ $slot }}</tbody>
            </table>
        </div>
    @endif
    <p class="border-t border-stone-100 px-5 py-3 text-xs leading-6 text-stone-500">مربعات الجدول تحدد سجلات الصفحة. في إدارة الكتالوج يمكنكِ اختيار كل النتائج من نطاق التحديد، ثم معاينة الإجراء وتأكيده.</p>
</section>
