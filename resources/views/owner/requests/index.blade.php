@extends('owner.layout')
@section('title', 'الطلبات المبدئية')
@section('content')
    <x-owner-page-heading title="الطلبات المبدئية"
        description="طلبات سجّلها العملاء للتواصل والمراجعة، ولا تُعد عمليات شراء مؤكدة. بيانات التواصل تظهر في التفاصيل المحمية فقط." />
    <form method="GET"
        class="mb-6 grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-4"
        data-search-form>
        <label class="text-xs">معرّف الطلب<input name="q" maxlength="100" minlength="2" value="{{ $filters['q'] ?? '' }}"
                dir="ltr" class="mt-2 block w-full rounded-lg border border-stone-300 p-3"></label>
        <label class="text-xs">حالة المراجعة<select name="review_status"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="">جميع حالات المراجعة</option>
                @foreach (\App\Domains\Orders\Domain\Review\ReviewStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['review_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs">العملة<select name="currency"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="">جميع العملات</option>
                @foreach (['USD', 'SYP'] as $currency)
                    <option @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs">مراجعة التجهيز عند الإرسال<select name="fulfillment"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="">الكل</option>
                <option value="1" @selected(($filters['fulfillment'] ?? '') === '1')>مطلوبة</option>
                <option value="0" @selected(($filters['fulfillment'] ?? '') === '0')>غير معلّمة</option>
            </select></label>
        <label class="text-xs">اكتمال المراجعة<select name="reviewed"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="">الكل</option>
                <option value="1" @selected(($filters['reviewed'] ?? '') === '1')>تمت المراجعة</option>
                <option value="0" @selected(($filters['reviewed'] ?? '') === '0')>لم تكتمل المراجعة</option>
            </select></label>
        @foreach (['from' => 'الإرسال من', 'to' => 'الإرسال إلى', 'activity_from' => 'آخر نشاط من', 'activity_to' => 'آخر نشاط إلى'] as $key => $label)
            <label class="text-xs">{{ $label }}<input type="date" name="{{ $key }}"
                    value="{{ $filters[$key] ?? '' }}" max="{{ now()->toDateString() }}"
                    class="mt-2 block w-full rounded-lg border border-stone-300 p-3"></label>
        @endforeach
        <label class="text-xs">الترتيب<select name="sort"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>الأحدث إرسالاً</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>الأقدم إرسالاً</option>
            </select></label>
        <label class="text-xs">حجم الصفحة<select name="per_page"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                @foreach ([10, 25, 50] as $size)
                    <option @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>
                @endforeach
            </select></label>
        @if (isset($filters['status']))
            <input type="hidden" name="status" value="submitted">
        @endif
        <div class="flex flex-wrap items-end gap-3"><button
                class="rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white">تطبيق المرشحات</button><a
                href="{{ route('owner.requests.index') }}" class="p-3 text-xs underline">إعادة ضبط</a><span
                data-search-progress role="status"></span></div>
    </form>
    <form id="review-bulk" data-review-bulk data-matching-count="{{ $records->total() }}" method="POST"
        action="{{ route('owner.requests.preview') }}"
        class="mb-5 grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 sm:grid-cols-3">
        @csrf @foreach ($filters as $key => $value)
            @if (!in_array($key, ['page', 'per_page', 'sort']))
                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
            @endif
        @endforeach
        <label class="text-xs">نطاق التحديد<select name="scope"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="ids">السجلات المحددة في الصفحة</option>
                <option value="matching">كل النتائج المطابقة ({{ $records->total() }})</option>
            </select></label>
        <label class="text-xs">الإجراء الجماعي<select name="target"
                class="mt-2 block w-full rounded-lg border border-stone-300 p-3">
                <option value="under_review">بدء أو استئناف المراجعة</option>
                <option value="awaiting_contact">وضع علامة يحتاج إلى تواصل</option>
            </select></label>
        <button class="self-end rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white">معاينة إجراء المراجعة…</button>
        <p class="text-xs leading-6 text-stone-500 sm:col-span-3">الحد 100 طلب. يجب أن تكون كل الحالات مؤهلة. إكمال المراجعة
            وإعادة فتحها إجراءان فرديان؛ المعاينة تحفظ المجموعة التي ترينها فقط ولا تضم طلبات جديدة لاحقاً.</p>
    </form>
    <x-owner-table title="الطلبات المبدئية" :empty="$records->isEmpty()">
        <x-slot:head>
            <tr>
                <th class="p-4"><input type="checkbox" data-select-page aria-label="تحديد الصفحة"></th>
                <th class="p-4">الطلب وتاريخ الإرسال</th>
                <th class="p-4">حالة الإرسال</th>
                <th class="p-4">المراجعة الداخلية</th>
                <th class="p-4">العملة والأصناف</th>
                <th class="p-4">التجهيز</th>
                <th class="p-4">آخر نشاط</th>
                <th class="p-4">التفاصيل</th>
            </tr>
        </x-slot:head>
        @foreach ($records as $row)
            <tr>
                <td class="p-4"><input type="checkbox" data-select-row name="ids[]" form="review-bulk"
                        value="{{ $row->id }}" aria-label="تحديد {{ $row->id }}"></td>
                <td class="p-4"><span dir="ltr"
                        class="block max-w-64 break-all font-mono text-xs">{{ $row->id }}</span><span dir="ltr"
                        class="mt-2 block text-xs">{{ $row->created_at }}</span></td>
                <td class="p-4 text-xs">مسجّل كطلب مبدئي</td>
                <td class="p-4 text-xs font-medium">
                    {{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($row->review_status)->label() }}</td>
                <td class="p-4 text-xs"><bdi dir="ltr">{{ $row->currency }}</bdi> · {{ $row->items_count }} صنف</td>
                <td class="p-4 text-xs">{{ $row->needs_review ? 'مطلوبة' : 'غير معلّمة' }}</td>
                <td class="p-4 text-xs"><bdi dir="ltr">{{ $row->last_activity_at ?? 'لا يوجد نشاط' }}</bdi></td>
                <td class="p-4"><a class="text-xs text-emerald-900 underline"
                        href="{{ route('owner.requests.show', ['id' => $row->id]) }}">عرض التفاصيل</a></td>
            </tr>
        @endforeach
    </x-owner-table>
    <x-owner-pagination :paginator="$records" />
@endsection
