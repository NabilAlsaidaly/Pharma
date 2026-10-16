<section class="mb-7 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
    <h2 class="mb-4 font-bold">متابعة مراجعة الطلبات المبدئية</h2>
    <p class="mb-5 text-xs leading-6 text-stone-500">حالات إدارية فقط، وليست طلبات شراء مؤكدة. الحالات إجمالية لكل
        التواريخ؛ نشاط المراجعة للفترة المختارة أدناه حسب تاريخ الحدث، وليس تاريخ إرسال الطلب.</p>
    @if ($reviewMetrics === null)
    <p role="status" class="text-sm text-stone-500">بيانات المراجعة غير متاحة قبل تجهيز مخططها.</p>@else<div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (['new_count' => 'جديد / لم تبدأ المراجعة', 'under_review' => 'قيد المراجعة', 'awaiting_contact' => 'يحتاج إلى تواصل', 'reviewed' => 'تمت المراجعة الإدارية', 'fulfillment' => 'طلبات بعلامة مراجعة تجهيز'] as $key => $label)
                <div class="rounded-xl bg-stone-50 p-4">
                    <p class="text-xs text-stone-500">{{ $label }}</p><strong
                        class="mt-3 block text-xl">{{ $reviewMetrics['counts'][$key] }}</strong>
                </div>
            @endforeach
            <div class="rounded-xl bg-emerald-50 p-4">
                <p class="text-xs">نشاط المراجعة خلال الفترة المختارة</p>
                <p class="mt-3 text-sm">{{ $reviewMetrics['activity']['events'] }} إجراء على
                    {{ $reviewMetrics['activity']['requests'] }} طلب مميز</p>
            </div>
        </div>
    @endif
</section>
