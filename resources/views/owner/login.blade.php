@extends('owner.layout')
@section('title', 'تسجيل الدخول')
@section('content')
<section class="mx-auto max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-9" aria-labelledby="login-title">
    <p class="mb-3 text-sm font-semibold text-emerald-800">وصول آمن</p>
    <h1 id="login-title" class="text-2xl font-bold">أهلاً بكِ في مساحة المالكة</h1>
    <p class="mt-3 text-sm leading-7 text-stone-600">استخدمي حساب الإدارة المخصص للوصول إلى إعدادات حسابكِ.</p>
    <form method="POST" action="{{ route('owner.login.submit') }}" class="mt-7 space-y-5">@csrf
        <x-owner-field name="email" label="البريد الإلكتروني" type="email" autocomplete="username" :maxlength="254" />
        <x-owner-field name="password" label="كلمة المرور" type="password" autocomplete="current-password" :maxlength="72" />
        <button class="w-full rounded-xl bg-emerald-900 px-5 py-3 font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">تسجيل الدخول</button>
    </form>
    <p class="mt-6 border-t border-stone-100 pt-5 text-sm leading-6 text-stone-600">استعادة كلمة المرور غير متاحة حالياً. عند فقد الوصول يلزم تدخل مشغّل موثوق.</p>
</section>
@endsection
