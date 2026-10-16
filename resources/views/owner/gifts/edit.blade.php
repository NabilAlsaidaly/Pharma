@extends('owner.layout')
@section('title', 'تحرير باقة هدايا')
@section('content')
    <x-owner-page-heading title="تحرير باقة هدايا"
        description="سعر الباقة مستقل عن أسعار مكوناتها. الحفظ لا ينشر التعديلات ولا يغير الطلبات السابقة." />
    @include('owner.gifts.tabs')
    @php($endpoint = route('owner.gifts.mutate', $record ? ['id' => $record->id] : []))
    <section class="mb-6 rounded-2xl border bg-white p-5">
        <h2 class="font-bold">
            {{ !$record ? 'باقة جديدة' : ($record->archived_at ? 'مؤرشفة — الاستعادة لا تنشر' : ($record->published_version ? 'منشورة بالإصدار ' . $record->published_version : 'مسودة غير منشورة')) }}
        </h2>
        <p class="mt-3 text-sm leading-7">التوفر المعلن لا يعني حجز منتجات أو تأكيد شراء. المنتجات المخفية أو المؤرشفة أو غير
            المتاحة تحجب الباقة عن العامة حتى عودة أهليتها. كل طلب لباقة واحدة بكمية واحدة.</p>
    </section>
    @if (!$record || !$record->archived_at)
        <form method="GET" class="mb-6 rounded-2xl border bg-white p-5">
            <label>البحث عن منتج لإضافته (حتى 50 منتجاً)<input name="catalog_q" maxlength="100"
                    value="{{ request('catalog_q') }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="mt-3 block">صفحة اختيارات المنتجات (50 في الصفحة)<input name="catalog_page" type="number" min="1" max="1000" value="{{ request('catalog_page', 1) }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="mt-3 block">تصفية اسم Variant<input name="variant_q" maxlength="100" value="{{ request('variant_q') }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="mt-3 block">صفحة اختيارات Variant (500 في الصفحة)<input name="variant_page" type="number" min="1" max="1000" value="{{ request('variant_page', 1) }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="mt-3 block">البحث عن مناسبة (حتى 100 نتيجة مع الاحتفاظ بالمحدد)<input name="occasion_q" maxlength="100" value="{{ request('occasion_q') }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="mt-3 block">صفحة اختيارات المناسبات (100 في الصفحة)<input name="occasion_page" type="number" min="1" max="1000" value="{{ request('occasion_page', 1) }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <button class="mt-3 rounded-xl border px-4 py-3">تحديث اختيارات الكتالوج والمناسبات</button>
            <p class="mt-2 text-sm">احفظي المسودة قبل البحث كي لا تفقدي تعديلات النموذج غير المحفوظة. لا يُختار Variant
                تلقائياً.</p>
        </form>
        <form method="POST" action="{{ $endpoint }}" class="space-y-6 rounded-2xl border bg-white p-5 sm:p-7"
            data-gift-editor>
            @include('owner.gifts.command', [
                'version' => $record->lock_version ?? '0',
                'action' => 'save',
            ])
            <div class="grid gap-5 sm:grid-cols-2">
                <label>اسم الباقة<input name="values[name]" maxlength="160" required value="{{ $draft['name'] }}"
                        class="mt-2 block w-full rounded-xl border p-3"></label>
                @if (!$record)
                    <label>رابط دائم (حروف إنكليزية صغيرة وأرقام وشرطات)<input name="slug" maxlength="80" required
                            pattern="[a-z0-9]+(-[a-z0-9]+)*" class="mt-2 block w-full rounded-xl border p-3"
                        dir="ltr"></label>@else<p class="break-all text-sm">الرابط الدائم: {{ $record->slug }}</p>
                @endif
                @foreach (['short_description' => ['الوصف المختصر', 500], 'description' => ['الوصف الكامل دون HTML', 12000], 'suitability' => ['وصف الملاءمة الاختياري', 1000]] as $field => $definition)
                    <label class="sm:col-span-2">{{ $definition[0] }}
                        <textarea name="values[{{ $field }}]" maxlength="{{ $definition[1] }}" rows="3"
                            class="mt-2 block w-full rounded-xl border p-3">{{ $draft[$field] ?? '' }}</textarea>
                    </label>
                @endforeach
                @foreach (['price_usd' => 'USD', 'price_syp' => 'SYP'] as $field => $label)
                    <label>سعر الباقة {{ $label }}<input name="values[{{ $field }}]" inputmode="decimal"
                            value="{{ $draft[$field] ?? '' }}" class="mt-2 block w-full rounded-xl border p-3"
                            dir="ltr"><span class="mt-2 block text-xs">فارغ = غير محدد؛ الصفر لا يسمح بطلب مجاني. حتى
                            منزلتين عشريتين.</span></label>
                @endforeach
                <label>التوفر المعلن<select name="values[availability]" class="mt-2 block w-full rounded-xl border p-3">
                        @foreach (['available' => 'متاح', 'on_request' => 'حسب الطلب — يحتاج مراجعة توفير', 'unavailable' => 'غير متاح'] as $key => $label)
                            <option value="{{ $key }}" @selected($draft['availability'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>ترتيب الباقة<input name="values[sort_order]" type="number" required min="0" max="999999999"
                        value="{{ $draft['sort_order'] }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            </div>
            <section aria-labelledby="gift-composition">
                <h2 id="gift-composition" class="mb-3 text-lg font-bold">مكونات الباقة</h2>
                <p class="mb-4 text-sm">حتى 50 مكوناً، كمية كل سطر 1–100. يمكن الجمع بين منتجات الكتالوج وعناصر يدوية. تعديل
                    الترتيب يُحفظ مسودة ثم يُنشر صراحة.</p>
                <input type="hidden" name="_components_present" value="1">
                <div class="space-y-4" data-gift-components>
                    @foreach ($draft['components'] as $index => $component)
                        @include('owner.gifts.component')
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap gap-3"><button type="button" data-gift-add="catalog"
                        class="rounded-xl border px-4 py-3">إضافة منتج من الكتالوج</button><button type="button"
                        data-gift-add="manual" class="rounded-xl border px-4 py-3">إضافة عنصر يدوي</button></div>
                <p role="status" data-gift-status class="mt-3 text-sm"></p>
                @foreach (['catalog', 'manual'] as $type)
                    <template data-gift-template="{{ $type }}">@include('owner.gifts.component', [
                        'index' => '__INDEX__',
                        'component' => ['type' => $type],
                    ])</template>
                @endforeach
                <noscript>إضافة وإزالة الأسطر تتطلب JavaScript؛ حفظ الأسطر الموجودة وترتيبها متاح بالنموذج.</noscript>
            </section>
            <fieldset>
                <legend class="font-bold">المناسبات المتاحة (اختيارية، حتى 20)</legend><input type="hidden"
                    name="_occasions_present" value="1">
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse($occasionChoices as $occasion)
                        <label class="flex min-w-0 items-start gap-3 rounded-xl border p-3"><input type="checkbox"
                                name="occasion_ids[]" value="{{ $occasion->id }}" @checked(in_array($occasion->id, array_column($draft['occasions'], 'occasion_id'), true))><span
                                class="break-words">{{ $occasion->name }}
                            {{ !$occasion->is_active || $occasion->archived_at ? '(غير نشطة — أزيلي الربط قبل النشر)' : '' }}</span></label>@empty
                        <p>أنشئي مناسبة وفعّليها أولاً إذا رغبتِ بتقديم اختيار مناسبة.</p>
                    @endforelse
                </div>
            </fieldset>
            <section>
                <h2 class="font-bold">صور المسودة</h2>
                <p class="mt-2 text-sm">حتى عشر صور. النشر يتطلب صورة ووصفاً بديلاً. حددي صورة أساسية واحدة فقط؛ عند عدم
                    تحديدها يستخدم الترتيب ثم المعرّف.</p><input type="hidden" name="_images_present" value="1">
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($draft['images'] as $i => $image)
                        <fieldset class="min-w-0 rounded-xl border p-4" data-gift-row>
                            <legend>صورة {{ $i + 1 }}</legend><img
                                src="{{ route('owner.gifts.media', ['id' => $image['media_id']]) }}"
                                alt="{{ $image['alt_text'] }}" class="mb-3 h-40 w-full rounded-xl object-contain"><input
                                type="hidden" name="images[{{ $i }}][media_id]"
                                value="{{ $image['media_id'] }}">
                            <label>الوصف البديل<input required maxlength="250" name="images[{{ $i }}][alt_text]"
                                    value="{{ $image['alt_text'] }}"
                                    class="mt-2 block w-full rounded-xl border p-3"></label><label
                                class="mt-3 block">الترتيب<input required type="number" min="0" max="999999999"
                                    name="images[{{ $i }}][sort_order]" value="{{ $image['sort_order'] }}"
                                    class="mt-2 block w-full rounded-xl border p-3"></label><label
                                class="mt-3 flex items-center gap-3"><input type="checkbox"
                                    name="images[{{ $i }}][is_primary]" value="1"
                                    @checked($image['is_primary'])>الصورة الأساسية</label><button type="button"
                                data-gift-remove class="mt-3 rounded-lg border px-4 py-2">إزالة الصورة من المسودة</button>
                        </fieldset>
                    @endforeach
                </div>
            </section><button class="rounded-xl bg-emerald-900 px-6 py-3 font-semibold text-white">حفظ المسودة</button>
        </form>
        @if ($record)
            <form method="POST" enctype="multipart/form-data" action="{{ $endpoint }}"
                class="mt-6 rounded-2xl border bg-white p-5">
                @include('owner.gifts.command', ['version' => $record->lock_version, 'action' => 'media'])
                <h2 class="font-bold">إضافة صورة</h2><label class="mt-3 block">صورة JPEG/PNG/WebP/GIF حتى 5MiB<input
                        type="file" name="image" required accept="image/jpeg,image/png,image/webp,image/gif"
                        class="mt-2 block w-full min-w-0"></label><label class="mt-3 block">وصف بديل<input
                        name="alt_text" required maxlength="250"
                        class="mt-2 block w-full rounded-xl border p-3"></label><button
                    class="mt-4 rounded-xl border px-5 py-3">رفع صورة خاصة للمسودة</button>
                <p class="mt-3 text-sm">احفظي التعديلات أولاً. الصورة لا تصبح عامة إلا بالنشر.</p>
            </form>
        @endif
    @endif
    @if ($record)
        <section class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ($record->archived_at ? ['restore' => 'استعادة كمسودة غير منشورة'] : ['publish' => 'نشر النسخة الحالية', 'unpublish' => 'إلغاء النشر', 'archive' => 'أرشفة الباقة'] as $action => $label)
                <form method="POST" action="{{ $endpoint }}" data-gift-confirm="{{ $label }}" class="rounded-2xl border bg-white p-5">
                    @include('owner.gifts.command', [
                        'version' => $record->lock_version,
                        'action' => $action,
                    ])<label class="flex items-start gap-3"><input type="checkbox" required
                            name="confirm" value="1"><span>أؤكد: {{ $label }}. يتغير العرض العام عند نجاح
                            الإجراء.</span></label><button
                        class="mt-4 rounded-xl border px-5 py-3">{{ $label }}</button></form>
            @endforeach
        </section>
        <dialog data-gift-confirmation aria-labelledby="gift-confirm-title" class="m-auto w-[calc(100%_-_2rem)] max-w-lg rounded-2xl border bg-white p-6 backdrop:bg-stone-950/40">
            <h2 id="gift-confirm-title" class="text-lg font-bold">تأكيد إجراء الباقة</h2>
            <p data-gift-confirm-label class="mt-4 break-words"></p>
            <p class="mt-3 text-sm leading-7">يتغير العرض العام عند نجاح الإجراء. لا تتغير حقائق طلبات الهدايا السابقة.</p>
            <div class="mt-5 flex flex-wrap gap-3"><button type="button" data-gift-cancel autofocus class="rounded-xl border px-5 py-3">العودة للمراجعة</button><button type="button" data-gift-accept class="rounded-xl bg-emerald-900 px-5 py-3 text-white">تأكيد التنفيذ</button></div>
        </dialog>
        <section class="mt-6 rounded-2xl border bg-white p-5">
            <h2 class="font-bold">معاينة المسودة الخاصة</h2>
            <p class="mt-3 whitespace-pre-line break-words">{{ $draft['description'] ?? 'لا يوجد وصف كامل.' }}</p>
            <ul class="mt-3 space-y-2">
                @foreach ($draft['components'] as $c)
                    <li class="break-words">{{ $c['name'] }} {{ $c['variant_name'] ?? '' }} × {{ $c['quantity'] }}
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="mt-6 rounded-2xl border bg-white p-5">
            <h2 class="font-bold">الإصدارات المحفوظة</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($history as $r)
                    <li><a class="underline"
                            href="{{ route('owner.gifts.edit', ['id' => $record->id, 'revision' => (string) $r->version]) }}">إصدار
                            {{ $r->version }} · {{ $r->action }} · {{ $r->created_at }}</a></li>
                @endforeach
            </ul>
            {{ $history->withQueryString()->links() }}
        </section>
        @if ($snapshot)
            <section class="mt-6 rounded-2xl border bg-white p-5">
                <h2 class="font-bold">لقطة تاريخية خاصة — إصدار {{ $snapshot['version'] }}</h2>
                <p class="mt-3 break-words">{{ $snapshot['name'] }}</p>
                <p class="mt-3 whitespace-pre-line break-words">{{ $snapshot['description'] }}</p>
                <p class="mt-3">USD {{ $snapshot['price_usd'] ?? '—' }} / SYP {{ $snapshot['price_syp'] ?? '—' }}</p>
                @foreach ($snapshot['components'] as $c)
                    <p class="mt-2 break-words">{{ $c['name'] }} {{ $c['variant_name'] ?? '' }} ×
                        {{ $c['quantity'] }}</p>
                    @endforeach @foreach ($snapshot['images'] as $im)
                        <img class="mt-3 h-40 w-full object-contain"
                            src="{{ route('owner.gifts.media', ['id' => $im['media_id']]) }}" alt="{{ $im['alt_text'] }}">
                    @endforeach
            </section>
        @endif
    @endif
@endsection
