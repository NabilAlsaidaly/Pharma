<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'مساحة المالكة') — سمية فارما</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
<header class="border-b border-stone-200 bg-white">
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-5 py-5">
        <div class="flex items-center gap-3"><span aria-hidden="true" class="flex size-11 items-center justify-center rounded-xl bg-emerald-900 text-xl font-bold text-white">س</span><div><p class="font-bold">سمية فارما</p><p class="text-sm text-stone-600">مساحة المالكة</p></div></div>
        @if (Auth::guard('owner')->check())
        <nav aria-label="التنقل الرئيسي" class="flex items-center gap-4 text-sm">
            <a class="rounded p-1 font-medium hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700" href="{{ route('owner.home') }}">الرئيسية</a>
            <a class="rounded p-1 font-medium hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-700" href="{{ route('owner.profile') }}">حسابي</a>
            <form method="POST" action="{{ route('owner.logout') }}">@csrf<button class="rounded-lg border border-stone-300 px-3 py-2 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-700">خروج</button></form>
        </nav>
        @endif
    </div>
</header>
<main id="main" class="mx-auto max-w-5xl px-5 py-10 sm:py-14">
    @if (session('status'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" aria-label="أخطاء النموذج" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"><p class="mb-2 font-semibold">يرجى مراجعة البيانات</p><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer class="mx-auto max-w-5xl px-5 pb-8 text-sm text-stone-500">دخول مخصص للمالكة · صلاحيات مستقلة عن حسابات العملاء</footer>
</body>
</html>
