@extends('owner.layout')
@section('title', 'تأكيد إجراء المراجعة')
@section('content')
    <x-owner-page-heading title="راجعي مجموعة الطلبات قبل التأكيد"
        description="المعاينة لا تغيّر البيانات. تنتهي بعد عشر دقائق؛ يُعاد التحقق من إصدارات الطلبات المحددة وحالاتها عند التنفيذ." />
    <section class="max-w-4xl rounded-2xl border border-amber-300 bg-white p-5 sm:p-7">
        <dl class="grid gap-5 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-stone-500">نوع السجلات</dt>
                <dd class="mt-2">طلبات مبدئية · مراجعة داخلية فقط</dd>
            </div>
            <div>
                <dt class="text-stone-500">العدد المحدد</dt>
                <dd class="mt-2 font-bold">{{ $targets->count() }} طلب</dd>
            </div>
            <div>
                <dt class="text-stone-500">الحالة المقترحة</dt>
                <dd class="mt-2">{{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($preview['target'])->label() }}
                </dd>
            </div>
            <div>
                <dt class="text-stone-500">نطاق التحديد</dt>
                <dd class="mt-2">
                    {{ $preview['scope'] === 'matching' ? 'كل النتائج المطابقة عند المعاينة، عبر الصفحات' : 'السجلات المحددة من الصفحة' }}
                </dd>
            </div>
        </dl>
        <p class="my-5 rounded-xl bg-amber-50 p-4 text-sm leading-7">ستُعدّل المراجعة الداخلية لهذه المجموعة كاملة فقط. لا
            يتغير الطلب الأصلي أو بيانات العميل أو الأصناف، ولا يُؤكَّد شراء أو دفع، ولا تُرسل رسائل. الطلبات الجديدة بعد
            المعاينة لا تُضم إلى المجموعة. إذا تغيّر طلب محدد تُرفض العملية كاملة وتلزم معاينة جديدة.</p>
        <details class="mb-6">
            <summary class="cursor-pointer text-sm">عرض الطلبات وحالاتها الحالية</summary>
            <ul class="mt-4 space-y-3 text-xs">
                @foreach ($targets as $row)
                    <li><bdi dir="ltr" class="break-all font-mono">{{ $row->id }}</bdi> ·
                        {{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($row->review_status)->label() }}</li>
                @endforeach
            </ul>
        </details>
        <form method="POST" action="{{ route('owner.requests.confirm') }}" class="space-y-5">@csrf<input type="hidden"
                name="token" value="{{ $token }}"><label class="flex items-start gap-3 text-sm"><input
                    type="checkbox" name="confirm" value="1" required class="mt-1"><span>راجعت العدد والحالات
                    والإجراء وأوافق على تنفيذه على المجموعة المحددة كاملة.</span></label><button
                class="rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white">تأكيد إجراء المراجعة</button><a
                href="{{ route('owner.requests.index') }}" class="ms-4 text-sm underline">إلغاء</a></form>
    </section>
@endsection
