@extends('owner.layout')
@section('title', 'التوصيل')
@section('content')
    <x-owner-page-heading title="التوصيل" description="تنسيق يدوي باستخدام العناوين الأصلية في الطلبات. جهات العمال خاصة ولا تملك حسابات أو إسناداً آلياً." />
    <nav class="mb-6 flex flex-wrap gap-4 text-sm" aria-label="إدارة التوصيل">
        <a class="underline" href="{{ route('owner.settings.group', ['group' => 'zones']) }}">مناطق ورسوم التوصيل الإرشادية</a>
        <a class="underline" href="{{ route('owner.delivery.history') }}">سجل إجراءات العمال</a>
        <a class="underline" href="{{ route('owner.delivery.create') }}">إضافة عامل توصيل</a>
    </nav>
    <p class="mb-5 text-sm leading-7">لا نطلب إدخال عنوان العميل مجدداً. استخدمي بيانات التواصل الأصلية في مراجعة الطلب أو الهدية. نشر المنطقة إرشادي فقط، والرسوم غير المحددة تحتاج التواصل، والصفر مجاني وفق النسخة المعروضة. لا تتغير أي مبالغ أو طلبات أو مدفوعات هنا.</p>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border bg-white p-5">
        <label class="min-w-0 flex-1 text-sm" for="worker-search">بحث بالاسم أو الهاتف<input id="worker-search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" class="mt-2 block w-full rounded-lg border p-3"></label>
        <label class="text-sm" for="worker-active">الحالة<select id="worker-active" name="active" class="mt-2 block rounded-lg border p-3"><option value="">الكل</option><option value="1" @selected(($filters['active'] ?? '') === '1')>نشط</option><option value="0" @selected(($filters['active'] ?? '') === '0')>غير نشط</option></select></label>
        <button class="rounded-lg border px-5 py-3 text-sm">تصفية</button>
    </form>
    <div class="space-y-3">
        @forelse($workers as $worker)
            <a class="block min-w-0 rounded-xl border bg-white p-5" href="{{ route('owner.delivery.edit', ['id' => $worker->id]) }}">
                <h2 class="break-words font-semibold">{{ $worker->name }}</h2><p class="mt-3 break-words text-sm" dir="ltr">{{ $worker->phone }}</p><p class="mt-3 text-xs">{{ $worker->is_active ? 'نشط' : 'غير نشط' }} · الإصدار {{ $worker->lock_version }}</p>
            </a>
        @empty<p class="rounded-xl border bg-white p-5 text-sm">لا توجد جهات تواصل بهذه الشروط. لا تُضاف جهات افتراضية.</p>
        @endforelse
    </div>
    <x-owner-pagination :paginator="$workers" />
@endsection
