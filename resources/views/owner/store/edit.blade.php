@extends('owner.layout')
@section('title', $spec['label'])
@section('content')
    <x-owner-page-heading :title="$spec['label']"
        description="الحفظ يُنشئ إصداراً في السجل. النشر مستقل، وتبقى النسخة المنشورة السابقة أثناء تعديل المسودة." />
    @include('owner.store.tabs')
    @php
        $parameters = [$section === 'settings' ? 'group' : 'type' => $type] + ($record ? ['id' => $record->id] : []);
        $endpoint = route('owner.' . $section . '.mutate', $parameters);
    @endphp
    <section class="mb-6 rounded-2xl border bg-white p-5 sm:p-7">
        <h2 class="font-bold">
            {{ !$record ? 'غير مُعدّ بعد' : ($record->archived_at ? 'مؤرشف — الاستعادة لا تنشره' : ($record->published_version ? 'منشور بالإصدار ' . $record->published_version : ($spec['public'] ? 'مسودة غير منشورة' : 'محفوظ للتجهيز — بانتظار الربط'))) }}
        </h2>
        <p class="mt-3 text-sm leading-7">
            {{ $spec['public'] ? 'النشر يجعل هذه المعلومات متاحة لواجهة المتجر. الحقول الفارغة اختيارية ولا تُستبدل بمعلومات مختلقة.' : 'السياسة أو الرسوم محفوظة للتجهيز، لكنها لا تُطبّق على الطلبات الحالية حتى اكتمال ربط الدفع والتوصيل والطلبات.' }}
        </p>
        @if ($type === 'currency')
            <p class="mt-3 text-sm">أسعار الدولار والليرة السورية مستقلة. هذه تسميات وترتيب للعرض فقط دون تحويل أو تعطيل
                عملة.</p>
        @endif
        @if ($type === 'payment')
            <p class="mt-3 text-sm">الدفع الكامل يعني 100.00%؛ والعربون بين 0.01 و99.99%. لا يجري تحصيل أو تحقق من الدفع هنا.
            </p>
        @endif
        @if ($type === 'zones')
            <p class="mt-3 text-sm">فارغ = رسوم غير محددة؛ الصفر مجاني وفق النسخة المنشورة فقط. كل عملة مستقلة والرسوم بانتظار التأكيد اليدوي ولا تُضاف للسلة أو الطلب. النشر إرشادي لا يضمن التغطية، ولا توجد أولوية أو مطابقة جغرافية آلية. ملاحظات التجهيز تبقى خاصة.</p>
            <a class="mt-3 inline-block text-sm underline" href="{{ route('owner.delivery.index') }}">التوصيل ودليل العمال الخاص</a>
        @endif
        @if ($type === 'policies')
            <p class="mt-3 text-sm">أدخلي النص الذي اعتمدتِه أنتِ. لا توجد سياسة قانونية افتراضية. النشر يتطلب موافقتك
                الصريحة.</p>
        @endif
    </section>
    @if (!$record || !$record->archived_at)
        <form method="POST" enctype="multipart/form-data" action="{{ $endpoint }}"
            class="mb-6 rounded-2xl border bg-white p-5 sm:p-7">
            @csrf<input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid7() }}"><input
                type="hidden" name="expected_version" value="{{ $record->lock_version ?? '0' }}"><input type="hidden"
                name="action" value="save">
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($spec['fields'] as $field => $definition)
                    <label class="min-w-0 text-sm {{ $definition[1] > 1000 ? 'sm:col-span-2' : '' }}"
                        for="setting-{{ $field }}">{{ $definition[0] }} @if (in_array($field, $spec['required']))
                            <span aria-hidden="true">*</span>
                        @endif
                        @if(in_array($definition[2] ?? '', ['payment_mode', 'currency', 'policy_slug']))
                            @php($choices = match($definition[2]) { 'payment_mode' => ['full' => 'دفع كامل', 'deposit' => 'عربون'], 'currency' => ['USD' => 'الدولار الأمريكي', 'SYP' => 'الليرة السورية'], default => ['privacy' => 'سياسة الخصوصية', 'terms' => 'الشروط والأحكام', 'returns' => 'سياسة الإرجاع والاستبدال'] })
                            <select id="setting-{{ $field }}" name="values[{{ $field }}]" required @disabled($record && $field === 'slug') class="mt-2 block w-full rounded-lg border p-3"><option value="">اختاري قيمة</option>@foreach($choices as $value => $label)<option value="{{ $value }}" @selected(($record->$field ?? null) === $value)>{{ $label }}</option>@endforeach</select>
                        @elseif ($definition[1] > 300)
                            <textarea id="setting-{{ $field }}" name="values[{{ $field }}]" maxlength="{{ $definition[1] }}"
                                rows="{{ $definition[1] > 1000 ? 7 : 3 }}" @required(in_array($field, $spec['required'])) @if(in_array($definition[2] ?? '', ['link', 'facebook', 'instagram', 'whatsapp', 'tiktok', 'youtube'])) dir="ltr" @endif class="mt-2 block w-full rounded-lg border p-3">{{ $record->$field ?? '' }}</textarea>
                        @else
                            <input id="setting-{{ $field }}" name="values[{{ $field }}]"
                                value="{{ $record->$field ?? '' }}" maxlength="{{ $definition[1] }}" @required(in_array($field, $spec['required'])) @readonly($record && $field === 'slug')
                                @if (in_array($definition[2] ?? '', [
                                        'email',
                                        'phone',
                                        'currency',
                                        'payment_mode',
                                        'percentage',
                                        'money',
                                        'order',
                                        'slug',
                                        'policy_slug',
                                    ])) dir="ltr" @endif
                                class="mt-2 block w-full rounded-lg border p-3">
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="my-4 text-xs leading-6 text-stone-500">النصوص بسيطة دون HTML. ترك حقل اختياري فارغاً يمحو قيمته في
                المسودة. @if($type === 'payment') حد النسبة منزلتان عشريتان. @endif @if($type === 'zones') حد الرسوم 9999999999999999.99. @endif @if(isset($spec['fields']['sort_order'])) الترتيب عدد صحيح من صفر إلى 999999999. @endif @if(isset($spec['fields']['slug'])) رابط الوثيقة ثابت بعد حفظها لحماية عناوينها المنشورة. @endif</p><button class="rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white">حفظ المسودة
                وإصدارها</button>
        </form>
    @endif
    @if ($record)
        @if (($spec['media'] ?? false) && !$record->archived_at)
            <form method="POST" enctype="multipart/form-data" action="{{ $endpoint }}"
                class="mb-6 rounded-2xl border bg-white p-5">
                @csrf<input type="hidden" name="operation_id"
                    value="{{ (string) \Illuminate\Support\Str::uuid7() }}"><input type="hidden" name="expected_version"
                    value="{{ $record->lock_version }}"><input type="hidden" name="action" value="media">
                <label class="block text-sm">{{ $type === 'profile' ? 'شعار المتجر' : 'صورة البنر' }}<input
                        class="mt-3 block max-w-full text-sm" type="file" name="image" required
                        accept="image/png,image/jpeg,image/webp,image/gif"></label>
                <p class="my-4 text-xs leading-6">صورة نقطية حتى 5 ميغابايت، 4096 لكل بُعد وأربعة ملايين بكسل. تُعاد
                    معالجتها لحمايتها، ولا تُنشر تلقائياً.</p><button class="rounded-lg border px-5 py-3 text-sm">حفظ الصورة
                    في المسودة</button>
            </form>
        @endif
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            @foreach ($record->archived_at ? ['restore' => 'استعادة دون نشر'] : ($spec['public'] ? ['publish' => 'نشر النسخة الحالية', 'unpublish' => 'إلغاء النشر'] : []) + ['archive' => 'أرشفة دون حذف السجل'] as $action => $label)
                <form method="POST" action="{{ $endpoint }}" class="rounded-xl border bg-white p-5">@csrf<input
                        type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid7() }}"><input
                        type="hidden" name="expected_version" value="{{ $record->lock_version }}"><input type="hidden"
                        name="action" value="{{ $action }}">
                    <h2 class="font-semibold">{{ $label }}</h2><label
                        class="my-4 flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="confirm"
                            value="1" required class="mt-1">أعتمد هذا الإجراء على السجل الحالي.
                        {{ $action === 'publish' ? ($type === 'zones' ? 'أوافق على عرض المنطقة ورسومها إرشادياً فقط، دون الملاحظات الخاصة أو التزام بالتوصيل.' : 'أوافق على إتاحة النص والصورة الحاليين للعملاء.') : 'لا يتغير أي طلب أو دفع أو شحنة.' }}</label><button
                        class="rounded-lg border border-emerald-900 px-4 py-2 text-sm">{{ $label }}</button>
                </form>
            @endforeach
        </div>
    @endif
    <section class="mb-6 rounded-2xl border bg-white p-5">
        <h2 class="mb-4 font-bold">معاينة خاصة — {{ $snapshot ? 'الإصدار ' . $snapshot->version : 'المسودة الحالية' }}</h2>
        @php($preview = $snapshot ?? $record)
        @if ($preview)
            @if (($spec['media'] ?? false) && $preview->image_path)
                <img class="mb-4 max-h-72 max-w-full rounded-xl object-contain"
                    src="{{ \App\Support\BusinessRaster::previewUrl($preview->image_path) }}"
                    alt="{{ $preview->alt_text ?? 'شعار المتجر في المعاينة الخاصة' }}">
            @endif
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ($spec['fields'] as $field => $definition)
                    <div class="min-w-0 {{ $definition[1] > 1000 ? 'sm:col-span-2' : '' }}">
                        <dt class="text-xs text-stone-500">{{ $definition[0] }}</dt>
                        <dd class="mt-2 whitespace-pre-line break-words text-sm leading-7">
                            {{ $preview->$field ?? 'غير مُعدّ' }}</dd>
                    </div>
                @endforeach
            </dl>
        @else<p class="text-sm">لا توجد بيانات للمعاينة بعد.</p>
        @endif
    </section>
    <section class="rounded-2xl border bg-white p-5">
        <h2 class="mb-4 font-bold">الإصدارات وسجل النشر</h2>
        <ul class="space-y-3 text-sm">
            @forelse($history as $revision)
                <li class="flex flex-wrap justify-between gap-3"><a class="underline"
                        href="{{ request()->fullUrlWithQuery(['revision' => (string) $revision->version]) }}">الإصدار
                        {{ $revision->version }} ·
                        {{ ['save' => 'حفظ مسودة', 'media' => 'حفظ صورة', 'publish' => 'نشر', 'unpublish' => 'إلغاء نشر', 'archive' => 'أرشفة', 'restore' => 'استعادة'][$revision->action] }}</a><time
                    dir="ltr">{{ $revision->created_at }}</time></li>@empty<li>لم يُسجّل تغيير بعد.</li>
            @endforelse
        </ul><x-owner-pagination :paginator="$history" />
    </section>
@endsection
