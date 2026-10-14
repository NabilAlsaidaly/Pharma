@extends('owner.layout')
@section('title', 'تعذر إكمال الطلب')
@section('content')
<section class="mx-auto max-w-md rounded-2xl border border-stone-200 bg-white p-8" role="alert">
    <h1 class="text-2xl font-bold">تعذر إكمال الطلب</h1>
    <p class="mt-4 leading-8 text-stone-600">@if ($statusCode === 429) محاولات كثيرة. يرجى الانتظار ثم المحاولة. @elseif ($statusCode === 419) انتهت صلاحية النموذج. حدّثي الصفحة وحاولي مجدداً. @elseif ($statusCode === 403) الوصول غير مسموح أو انتهت صلاحية الجلسة. @else يرجى المحاولة لاحقاً أو مراجعة المشغّل الموثوق. @endif</p>
    @if (Route::has('owner.login'))<a class="mt-6 inline-block rounded-lg p-2 font-semibold text-emerald-800 focus-visible:outline-2" href="{{ route('owner.login') }}">العودة إلى تسجيل الدخول</a>@endif
</section>
@endsection
