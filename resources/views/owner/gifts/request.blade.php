@extends('owner.layout')
@section('title', 'تفاصيل طلب هدية')
@section('content')
    <x-owner-page-heading title="تفاصيل طلب هدية"
        description="معلومات خاصة؛ الطلب مبدئي ولا يؤكد شراءً أو دفعاً أو حجز مخزون أو شحناً." />
    @include('owner.gifts.tabs')
    @php($review = $detail['review'])
    @php($state = \App\Domains\Orders\Domain\Review\ReviewStatus::from($review->review_status ?? 'new'))
    <section class="rounded-2xl border bg-white p-5">
        <h2 class="break-words text-lg font-bold">{{ $detail['package_name'] }}</h2>
        <p class="mt-3 break-all">{{ $detail['id'] }}</p>
        <p class="mt-3">النسخة {{ $detail['published_version'] }} · {{ $detail['currency'] }}
            {{ $detail['display_price_snapshot'] }} — سعر تاريخي غير ملزم ولا يشمل رسوماً محسوبة</p>
        <p class="mt-3">{{ $detail['requires_fulfillment_review'] ? 'يحتاج مراجعة توفير' : 'التوفر المعلن لا يعني حجزاً' }}
            · {{ $state->label() }}</p>
        <p class="mt-3">{{ $detail['created_at'] }}</p>
    </section>
    <section class="mt-6 grid gap-5 rounded-2xl border bg-white p-5 sm:grid-cols-2">
        <h2 class="font-bold sm:col-span-2">البيانات الأصلية الخاصة</h2>
        @foreach (['account_email' => 'بريد حساب العميل', 'sender_display_name' => 'اسم عرض المرسل', 'occasion_name' => 'المناسبة', 'recipient_name' => 'المستلم', 'recipient_phone' => 'الهاتف', 'recipient_governorate' => 'المحافظة', 'recipient_city' => 'المدينة', 'recipient_address' => 'العنوان', 'recipient_notes' => 'ملاحظات المستلم', 'message' => 'رسالة الإهداء'] as $field => $label)
            <div class="min-w-0">
                <h3 class="text-sm font-semibold">{{ $label }}</h3>
                <p class="mt-2 whitespace-pre-line break-words">{{ $detail[$field] ?? '—' }}</p>
            </div>
        @endforeach
    </section>
    <section class="mt-6 rounded-2xl border bg-white p-5">
        <h2 class="font-bold">تركيب الهدية المقبول تاريخياً</h2>
        @foreach ($detail['components'] as $c)
            <p class="mt-3 break-words">{{ $c->name }} {{ $c->variant_name }} × {{ $c->quantity }} @if ($c->description)
                    — {{ $c->description }}
                @endif
            </p>
        @endforeach
    </section>
    <section class="mt-6 grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('owner.gifts.review', ['id' => $detail['id']]) }}"
            class="space-y-4 rounded-2xl border bg-white p-5">@csrf<input type="hidden" name="operation_id"
                value="{{ (string) \Illuminate\Support\Str::uuid7() }}"><input type="hidden" name="expected_version"
                value="{{ (string) ($review->lock_version ?? '0') }}"><input type="hidden" name="kind"
                value="transition">
            <h2 class="font-bold">تغيير حالة المراجعة</h2><label class="block">الحالة التالية<select name="target"
                    required class="mt-2 block w-full rounded-xl border p-3">
                    @foreach (\App\Domains\Orders\Domain\Review\ReviewStatus::cases() as $next)
                        @if ($state->allows($next))
                            <option value="{{ $next->value }}">{{ $next->label() }}</option>
                        @endif
                    @endforeach
                </select></label><label
                class="block">{{ $state->value === 'reviewed' ? 'سبب إعادة الفتح (إلزامي)' : 'ملاحظة اختيارية' }}
                <textarea name="note" maxlength="2000" @required($state->value === 'reviewed') class="mt-2 block w-full rounded-xl border p-3"></textarea>
            </label><button class="rounded-xl border px-5 py-3">حفظ الحالة</button>
        </form>
        <form method="POST" action="{{ route('owner.gifts.review', ['id' => $detail['id']]) }}"
            class="space-y-4 rounded-2xl border bg-white p-5">@csrf<input type="hidden" name="operation_id"
                value="{{ (string) \Illuminate\Support\Str::uuid7() }}"><input type="hidden" name="expected_version"
                value="{{ (string) ($review->lock_version ?? '0') }}">
            <h2 class="font-bold">ملاحظة أو متابعة يدوية خاصة</h2><label class="block">نوع السجل<select name="kind"
                    class="mt-2 block w-full rounded-xl border p-3">
                    <option value="note">ملاحظة داخلية</option>
                    <option value="follow_up">وصف متابعة يدوية</option>
                </select></label><label class="block">النص
                <textarea name="note" required maxlength="2000" class="mt-2 block w-full rounded-xl border p-3"></textarea>
            </label>
            <p class="text-sm">وصفك فقط؛ لا تُرسل رسالة ولا يُؤكد تواصل أو تسليم آلياً.</p><button
                class="rounded-xl border px-5 py-3">إضافة للسجل الخاص</button>
        </form>
    </section>
    <section class="mt-6 rounded-2xl border bg-white p-5">
        <h2 class="font-bold">سجل المراجعة التراكمي</h2>
        @forelse($events as $event)
            <article class="mt-4 border-t pt-4">
                <p>{{ $event->actor_name }} · {{ $event->created_at }} · إصدار {{ $event->resulting_version }}</p>
                <p class="mt-2">
                    {{ $event->kind === 'note' ? 'ملاحظة' : ($event->kind === 'follow_up' ? 'متابعة يدوية' : 'انتقال حالة') }}
                    · {{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($event->new_status)->label() }}</p>
                <p class="mt-2 whitespace-pre-line break-words">{{ $event->note }}</p>
        </article>@empty<p class="mt-3">جديد دون سجل مراجعة؛ عرض الصفحة لا يُنشئ سجلاً.</p>
        @endforelse{{ $events->withQueryString()->links() }}
    </section>
@endsection
