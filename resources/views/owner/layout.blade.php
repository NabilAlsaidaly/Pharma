<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'مساحة المالكة') — سمية فارما</title>@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body data-owner-portal class="min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
    <a href="#main"
        class="sr-only z-50 rounded-lg bg-emerald-950 p-4 text-white focus:not-sr-only focus:fixed focus:start-4 focus:top-4">انتقال
        إلى المحتوى</a>
    @if ($portalOwner && count($navigationItems))
        <div class="owner-shell lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]" data-owner-shell>
            <aside id="owner-sidebar"
                class="owner-sidebar border-e border-stone-200 bg-white p-5 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto"
                aria-label="القائمة الرئيسية">
                <div class="mb-8 flex items-center justify-between gap-3"><a href="{{ route('owner.dashboard') }}"
                        class="flex items-center gap-3 rounded-xl p-1"><span aria-hidden="true"
                            class="grid size-11 place-items-center rounded-xl bg-emerald-900 text-xl font-bold text-white">س</span><span><strong
                                class="block">سمية فارما</strong><span class="text-xs text-stone-500">مساحة
                                المالكة</span></span></a><button type="button" data-close-sidebar
                        class="hidden rounded-lg p-2 lg:!hidden" aria-label="إغلاق القائمة"><x-owner-icon
                            name="close" /></button></div>
                <nav class="space-y-6">
                    @foreach (collect($navigationItems)->groupBy('group') as $group => $items)
                        <section aria-label="{{ $group }}">
                            <p class="mb-2 px-3 text-xs font-semibold text-stone-500">{{ $group }}</p>
                            <ul class="space-y-1">
                                @foreach ($items as $item)
                                    <li><a href="{{ route($item['route']) }}"
                                            @if ($item['active']) aria-current="page" @endif
                                            class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ $item['active'] ? 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-100' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-950' }}"><x-owner-icon
                                                :name="$item['icon']" />{{ $item['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </nav>
                <div class="mt-8 rounded-xl border border-stone-200 bg-stone-50 p-4 text-xs leading-6 text-stone-600">
                    يمكنكِ إدارة الكتالوج ومراجعة الطلبات المبدئية وتسجيل الملاحظات الداخلية.</div>
                <form method="POST" action="{{ route('owner.logout') }}" class="mt-5">@csrf<button
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-stone-300 px-4 py-3 text-sm"><x-owner-icon
                            name="logout" />تسجيل الخروج</button></form>
            </aside>
            <button type="button" data-sidebar-overlay hidden
                class="owner-overlay fixed inset-0 z-30 bg-stone-950/40 lg:!hidden" aria-label="إغلاق القائمة"></button>
            <div class="min-w-0">
                <header class="border-b border-stone-200 bg-white px-5 py-4 sm:px-8">
                    <div class="flex flex-wrap items-center gap-4"><button type="button" data-open-sidebar
                            class="hidden rounded-lg border border-stone-200 p-2 lg:!hidden"
                            aria-controls="owner-sidebar" aria-expanded="false"
                            aria-label="فتح القائمة الرئيسية"><x-owner-icon name="menu" /></button>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-stone-500">مساحة المالكة / @yield('title', 'مساحة العمل')</p>
                            <p class="mt-1 text-lg font-semibold">@yield('title', 'مساحة العمل')</p>
                        </div><a href="{{ route('owner.profile') }}"
                            class="flex items-center gap-2 rounded-xl px-2 py-1 text-sm"><span aria-hidden="true"
                                class="grid size-9 place-items-center rounded-full bg-stone-100 text-emerald-900"><x-owner-icon
                                    name="profile" /></span><span
                                class="max-w-36 break-words">{{ $portalOwner->display_name }}</span></a>
                    </div>
                    <form action="{{ route('owner.search') }}" method="GET" data-search-form class="mt-4 flex gap-2">
                        <label for="portal-search" class="sr-only">البحث في المنتجات والعلامات والاهتمامات والطلبات
                            المبدئية</label><input id="portal-search" type="search" name="q" required
                            minlength="2" maxlength="100" placeholder="ابحثي باسم منتج أو علامة أو معرّف طلب…"
                            value="{{ request()->routeIs('owner.search') ? $term ?? '' : '' }}"
                            class="min-w-0 flex-1 rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm"><button
                            class="rounded-xl bg-emerald-900 px-5 py-3 text-sm font-semibold text-white">بحث</button><span
                            data-search-progress role="status" class="sr-only"></span></form>
                </header>
                <main id="main" tabindex="-1" class="min-w-0 px-5 py-8 sm:px-8 lg:py-10">
                    @include('owner.feedback') @yield('content')</main>
                <footer class="px-5 pb-6 text-xs leading-6 text-stone-500 sm:px-8">سمية فارما · مساحة المالكة لإدارة الكتالوج ومراجعة الطلبات المبدئية.
                    تتيح صفحة حسابي تعديل الاسم وكلمة المرور.</footer>
            </div>
        </div>
    @else
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-5 py-5"><span aria-hidden="true"
                    class="grid size-11 place-items-center rounded-xl bg-emerald-900 text-xl font-bold text-white">س</span>
                <div>
                    <p class="font-bold">سمية فارما</p>
                    <p class="text-sm text-stone-600">مساحة المالكة</p>
                </div>
            </div>
        </header>
        <main id="main" tabindex="-1" class="mx-auto max-w-5xl px-5 py-10 sm:py-14">@include('owner.feedback')
            @yield('content')</main>
        <footer class="mx-auto max-w-5xl px-5 pb-8 text-sm text-stone-500">دخول مخصص للمالكة · صلاحيات مستقلة عن حسابات
            العملاء</footer>
    @endif
</body>

</html>
