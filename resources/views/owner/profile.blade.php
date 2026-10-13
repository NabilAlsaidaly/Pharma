@extends('owner.layout')
@section('title', 'حسابي')
@section('content')
<h1 class="text-3xl font-bold">حسابي</h1><p class="mt-3 text-stone-600">معلومات الحساب وأمان الوصول</p>
<div class="mt-8 grid items-start gap-6 lg:grid-cols-2">
    <section class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8" aria-labelledby="identity-title">
        <h2 id="identity-title" class="text-xl font-semibold">معلومات الحساب</h2>
        <dl class="mt-5 space-y-3 text-sm"><div><dt class="text-stone-600">الصلاحية</dt><dd class="mt-1 font-medium">المالكة</dd></div><div><dt class="text-stone-600">البريد الإلكتروني</dt><dd dir="ltr" class="mt-1 break-all text-right font-medium">{{ $owner->email }}</dd></div></dl>
        <p class="mt-4 text-sm leading-7 text-stone-600">تغيير البريد الإلكتروني سيتوفر بعد تفعيل خدمة التحقق من البريد.</p>
        <form method="POST" action="{{ route('owner.profile.update') }}" class="mt-6 space-y-5">@csrf @method('PATCH')
            <x-owner-field name="display_name" label="اسم العرض" autocomplete="name" :value="$owner->display_name" />
            <button class="rounded-xl bg-emerald-900 px-5 py-3 font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">حفظ الاسم</button>
        </form>
    </section>
    <section class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8" aria-labelledby="security-title">
        <h2 id="security-title" class="text-xl font-semibold">تغيير كلمة المرور</h2>
        <p class="mt-3 text-sm leading-7 text-stone-600">استخدمي عبارة مرور من 12 محرفاً على الأقل وبحد أقصى 72 بايت. يتطلب التغيير كلمة المرور الحالية، ويُلغي صلاحية الجلسات السابقة.</p>
        <form method="POST" action="{{ route('owner.password.update') }}" class="mt-6 space-y-5">@csrf @method('PUT')
            <x-owner-field name="current_password" label="كلمة المرور الحالية" type="password" autocomplete="current-password" :maxlength="72" />
            <x-owner-field name="password" label="كلمة المرور الجديدة" type="password" autocomplete="new-password" :maxlength="72" />
            <x-owner-field name="password_confirmation" label="تأكيد كلمة المرور الجديدة" type="password" autocomplete="new-password" :maxlength="72" />
            <button class="rounded-xl bg-emerald-900 px-5 py-3 font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">تحديث كلمة المرور</button>
        </form>
    </section>
</div>
@endsection
