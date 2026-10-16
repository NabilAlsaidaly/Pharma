@extends('owner.layout')
@section('title', 'تفاصيل الطلب المبدئي')
@section('content')
    <x-owner-page-heading title="تفاصيل الطلب المبدئي"
        description="هذا طلب سجّله العميل للتواصل والمراجعة، ولا يُعد طلب شراء مؤكداً. اكتمال المراجعة لا يعني تأكيد الطلب أو استلام الدفع." />
    <a class="mb-5 inline-block text-sm text-emerald-900 underline" href="{{ route('owner.requests.index') }}">العودة إلى
        الطلبات المبدئية</a>
    <section class="mb-6 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
        <h2 class="mb-5 font-bold">الطلب الأصلي كما سُجّل</h2>
        <p class="mb-5 text-sm">حالة الإرسال: مسجّل كطلب مبدئي</p>
        <dl class="grid gap-5 text-sm sm:grid-cols-2">
            @foreach (['id' => 'معرّف الطلب', 'created_at' => 'تاريخ الإرسال', 'currency' => 'العملة المسجلة', 'full_name' => 'اسم جهة التواصل', 'phone' => 'رقم الهاتف', 'email' => 'البريد الإلكتروني', 'governorate' => 'المحافظة', 'city' => 'المدينة', 'address' => 'العنوان', 'notes' => 'ملاحظات العميل الأصلية'] as $field => $label)
                <div class="min-w-0">
                    <dt class="text-xs text-stone-500">{{ $label }}</dt>
                    <dd class="mt-2 whitespace-pre-line break-words" @if (in_array($field, ['id', 'created_at', 'currency', 'phone', 'email'])) dir="ltr" @endif>
                        {{ $record->$field ?? 'لا توجد ملاحظات' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
    <section class="mb-6 min-w-0 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
        <h2 class="mb-4 font-bold">الأصناف كما سُجلت عند إرسال الطلب</h2>
        <p class="mb-4 text-xs leading-6 text-stone-500">هذه معرّفات وكميات تاريخية، وليست أسماء أو أسعاراً محفوظة. تبقى
            ظاهرة عند أرشفة المنتج أو الخيار. لا توجد قيمة مالية مستحقة هنا.</p>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="أصناف الطلب التاريخية">
            <table class="w-full min-w-[40rem] text-right text-xs">
                <thead>
                    <tr>
                        <th class="p-3">ترتيب السطر</th>
                        <th class="p-3">المنتج المسجّل</th>
                        <th class="p-3">الخيار المسجّل</th>
                        <th class="p-3">الكمية</th>
                        <th class="p-3">مراجعة التجهيز عند الإرسال</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($related as $item)
                        <tr class="border-t border-stone-100">
                            <td class="p-3">{{ $item->line_order }}</td>
                            <td dir="ltr" class="max-w-64 break-all p-3 font-mono">{{ $item->product_id }}</td>
                            <td dir="ltr" class="max-w-64 break-all p-3 font-mono">
                                {{ $item->variant_id ?? 'بدون خيار' }}</td>
                            <td class="p-3">{{ $item->quantity }}</td>
                            <td class="p-3">{{ $item->requires_fulfillment_review ? 'مطلوبة' : 'غير معلّمة للمراجعة' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div><x-owner-pagination :paginator="$related" />
    </section>
    <section class="mb-6 rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
        <h2 class="mb-4 font-bold">المراجعة الإدارية</h2>
        <p class="mb-4 text-sm font-semibold">{{ $reviewStatus->label() }} @if (!$review)
                <span class="text-xs font-normal text-stone-500">· لم يُسجّل نشاط داخلي بعد</span>
            @endif
        </p>
        <p class="mb-5 text-xs leading-7 text-stone-500">حالة المراجعة توضّح العمل الداخلي فقط. اكتمال المراجعة لا يعني
            تأكيد الطلب أو استلام الدفع.</p>
        <dl class="mb-5 grid gap-4 text-xs sm:grid-cols-2">
            @foreach (['reviewer' => 'آخر من غيّر حالة المراجعة', 'review_started_at' => 'بداية المراجعة', 'review_completed_at' => 'اكتمال المراجعة الحالية', 'last_activity_at' => 'آخر نشاط داخلي'] as $field => $label)
                <div>
                    <dt class="text-stone-500">{{ $label }}</dt>
                    <dd class="mt-2" @if($field !== 'reviewer') dir="ltr" @endif>{{ $review->$field ?? 'لم يُسجّل' }}</dd>
                </div>
            @endforeach
        </dl>
        <form method="POST" action="{{ route('owner.requests.review', ['id' => $record->id]) }}"
            class="grid gap-4 sm:grid-cols-2">@csrf<input type="hidden" name="operation_id"
                value="{{ $operations['transition'] }}"><input type="hidden" name="expected_version"
                value="{{ $review->lock_version ?? '0' }}"><input type="hidden" name="kind" value="transition"><label
                class="text-sm">الإجراء<select name="target" required
                    class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                    @foreach ($transitions as $next)
                        <option value="{{ $next->value }}">{{ $next->label() }}</option>
                    @endforeach
                </select>
            </label><label
                class="text-sm">{{ $reviewStatus->value === 'reviewed' ? 'سبب إعادة فتح المراجعة (مطلوب)' : 'ملاحظة مرافقة (اختيارية)' }}
                <textarea name="note" rows="3" maxlength="2000" @required($reviewStatus->value === 'reviewed')
                    class="mt-2 block w-full rounded-lg border border-stone-300 p-3"></textarea>
            </label><button class="justify-self-start rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white">حفظ حالة
                المراجعة</button></form>
    </section>
    <section class="mb-6 grid gap-6 sm:grid-cols-2">
        @foreach (['note' => 'ملاحظة داخلية', 'follow_up' => 'تسجيل متابعة يدوية'] as $kind => $label)
            <form method="POST" action="{{ route('owner.requests.review', ['id' => $record->id]) }}"
                class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5">@csrf<input type="hidden"
                    name="operation_id" value="{{ $operations[$kind] }}"><input type="hidden" name="expected_version"
                    value="{{ $review->lock_version ?? '0' }}"><input type="hidden" name="kind"
                    value="{{ $kind }}">
                <h2 class="mb-3 font-bold">{{ $label }}</h2>
                <p class="mb-4 text-xs leading-6 text-stone-500">
                    {{ $kind === 'note' ? 'خاصة بالمالكة ولا تظهر للعميل. تُضاف إلى السجل دون تعديل الملاحظات السابقة.' : 'وثّقي ما قمتِ به يدوياً. هذا السجل لا يرسل رسالة ولا يثبت وصول اتصال إلى العميل.' }}
                </p><label class="text-sm">نص {{ $label }}
                    <textarea name="note" required rows="4" maxlength="2000"
                        class="mt-2 block w-full rounded-lg border border-stone-300 p-3"></textarea>
                </label>
                <p class="my-3 text-xs text-stone-500">حتى 2000 محرف. لا تضيفي كلمات مرور أو بيانات تواصل مكررة.</p><button
                    class="rounded-lg border border-emerald-900 px-4 py-3 text-sm">حفظ {{ $label }}</button>
            </form>
        @endforeach
    </section>
    <section class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
        <h2 class="mb-3 font-bold">سجل المراجعات والنشاط الداخلي</h2>
        <p class="mb-5 text-xs text-stone-500">الأحدث أولاً. يعرض الإجراءات المسجّلة ومن نفّذها ومتى، ولا يمكن تعديل أحداثه
            أو حذفها.</p>
        <ol class="space-y-5">
            @forelse($events as $event)
                <li class="min-w-0 rounded-xl border border-stone-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3 text-xs">
                        <strong>{{ ['note_added' => 'ملاحظة داخلية', 'follow_up_recorded' => 'متابعة يدوية مسجّلة', 'review_started' => 'بدء المراجعة', 'status_changed' => 'تغيير حالة المراجعة', 'review_completed' => 'اكتمال المراجعة الإدارية', 'review_reopened' => 'إعادة فتح المراجعة'][$event->event_type] }}</strong><time
                            dir="ltr">{{ $event->created_at }}</time></div>
                    <p class="mt-3 text-xs text-stone-500">{{ $event->author }} ·
                        {{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($event->previous_status)->label() }} ←
                        {{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($event->new_status)->label() }}</p>
                    @if ($event->note)
                        <p class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ $event->note }}</p>
                    @endif
                </li>
            @empty<li class="text-sm text-stone-500">لا توجد ملاحظات أو أحداث مراجعة مسجّلة.</li>
            @endforelse
        </ol><x-owner-pagination :paginator="$events" />
    </section>
@endsection
