@extends('owner.layout')
@section('title', 'الرئيسية')
@section('content')
<p class="mb-3 text-sm font-semibold text-emerald-800">مساحة عملكِ</p>
<h1 class="text-3xl font-bold">أهلاً، {{ $owner->display_name }}</h1>
<p class="mt-4 max-w-2xl leading-8 text-stone-600">حسابكِ محمي ومستقل عن حسابات العملاء. يمكنكِ الآن إدارة اسم العرض وكلمة المرور.</p>
<section class="mt-8 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
    <h2 class="text-lg font-semibold">إعدادات الحساب</h2>
    <p class="mt-2 text-sm leading-7 text-stone-600">راجعي معلومات حسابكِ أو حدّثي كلمة المرور بأمان.</p>
    <a href="{{ route('owner.profile') }}" class="mt-5 inline-block rounded-xl bg-emerald-900 px-5 py-3 font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700">عرض حسابي</a>
</section>
<p class="mt-6 text-sm leading-7 text-stone-600">أقسام إدارة العمل ستُضاف في المراحل اللاحقة. هذه المرحلة مخصصة لهوية المالكة والوصول الآمن فقط.</p>
@endsection
