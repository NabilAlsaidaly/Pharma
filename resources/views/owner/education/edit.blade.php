@extends('owner.layout')
@section('title', 'تحرير المقال')
@section('content')
    <x-owner-page-heading title="تحرير المقال"
        description="يمكنك تعديل المقال وحفظه دون أن تظهر التعديلات للزوار حتى تعيدي نشره." />
    @include('owner.education.tabs')
    @php($endpoint = route('owner.education.mutate', $record ? ['id' => $record->id] : []))
    <section class="mb-6 rounded-2xl border bg-white p-5">
        <p class="font-bold">
            {{ !$record ? 'مسودة جديدة' : ($record->archived_at ? 'مؤرشف — الاستعادة لا تنشر' : ($record->published_version ? 'منشور بالإصدار ' . $record->published_version . ' · مسودة ' . $record->lock_version : 'مسودة غير منشورة')) }}
        </p>
        <p class="mt-3 leading-7">يجب مراجعة صحة المعلومات الصحية والتجميلية قبل نشرها؛ النظام لا يتحقق تلقائياً من صحتها
            الطبية.</p>
    </section>
    @if (!$record || !$record->archived_at)
        <form method="GET" class="education-panel mb-6 rounded-2xl border bg-white p-5" data-education-directory>
            <p>احفظي المسودة قبل تحديث اختيارات الكتالوج والفئات. الربط لا يغير أسعار المنتجات أو توفرها.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['product' => 'المنتجات', 'concern' => 'الاهتمامات', 'category' => 'الفئات'] as $kind => $label)
                    <fieldset>
                        <legend>{{ $label }}</legend><label>بحث<input name="{{ $kind }}_q" maxlength="100"
                                value="{{ request($kind . '_q') }}"></label><label>صفحة الاختيارات (50 في الصفحة)<input
                                name="{{ $kind }}_page" type="number" min="1" max="1000"
                                value="{{ request($kind . '_page', 1) }}"></label>
                    </fieldset>
                @endforeach
            </div><button>تحديث الاختيارات</button>
        </form>
        <form method="POST" enctype="multipart/form-data" action="{{ $endpoint }}"
            class="education-panel space-y-6 rounded-2xl border bg-white p-5 sm:p-7" data-education-editor>
            @include('owner.education.command', [
                'version' => $record->lock_version ?? '0',
                'action' => 'save',
            ])
            <input type="hidden" name="_relations_present" value="1">
            <div class="grid gap-4 sm:grid-cols-2">
                <label>عنوان المقال<input name="values[title]" required maxlength="160"
                        value="{{ $draft['title'] }}"></label>
                @if (!$record)
                    <label>الرابط الدائم<input name="slug" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                        dir="ltr"></label>@else<p class="break-all">الرابط الدائم: {{ $record->slug }}</p>
                @endif
                <label class="sm:col-span-2">مقتطف اختياري
                    <textarea name="values[excerpt]" maxlength="500" rows="3">{{ $draft['excerpt'] }}</textarea>
                </label>
                <label>عنوان محركات البحث<input name="values[seo_title]" maxlength="160"
                        value="{{ $draft['seo_title'] }}"></label>
                <label>وصف محركات البحث
                    <textarea name="values[meta_description]" maxlength="300">{{ $draft['meta_description'] }}</textarea>
                </label>
                <label>الفئة الأساسية (اختيارية)<select name="values[category_id]">
                        <option value="">دون فئة</option>
                        @foreach ($choices['category'] as $choice)
                            <option value="{{ $choice->id }}" @selected($draft['category_id'] === $choice->id)>{{ $choice->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>نطاق المراجعة التحريرية<select name="values[editorial_scope]">
                        <option value="">غير محدد — مسودة فقط</option>
                        <option value="general" @selected($draft['editorial_scope'] === 'general')>معلومات عامة</option>
                        <option value="health_related" @selected($draft['editorial_scope'] === 'health_related')>ادعاءات صحية أو تجميلية مؤثرة — تحتاج
                            مصادر ومراجعة</option>
                    </select></label>
                <label>ترتيب العرض<input name="values[sort_order]" type="number" required min="0" max="999999999"
                        value="{{ $draft['sort_order'] }}"></label>
                <label class="flex items-center gap-3"><input name="values[is_featured]" type="checkbox" value="1"
                        @checked($draft['is_featured'])>مقال مميز تحريرياً</label>
            </div>
            <section aria-labelledby="education-body-title">
                <h2 id="education-body-title" class="text-lg font-bold">محتوى المقال</h2>
                <p class="my-3 text-sm">أضيفي كتل الفقرات والعناوين والقوائم والصور. حتى100كتلة و40000محرف، وحتى5صور داخلية.
                    حددي نصاً ثم استخدمي عريض أو مائل أو رابط. اللصق نص فقط.</p>
                <input type="hidden" name="body_document"
                    value="{{ \App\Domains\Content\Domain\Education\ArticleDocument::encode($draft['body']) }}"
                    data-education-document>
                <div class="space-y-4" data-education-blocks></div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['paragraph' => 'فقرة', 'heading' => 'عنوان فرعي', 'unordered' => 'قائمة نقطية', 'ordered' => 'قائمة رقمية', 'image' => 'صورة داخلية'] as $type => $label)
                        <button type="button" data-education-add="{{ $type }}" disabled>إضافة {{ $label }}</button>
                    @endforeach
                </div>
                <p class="mt-3 text-sm" role="status" data-education-status>جارٍ تهيئة محرر الكتل…</p>
                <noscript>تحرير كتل المحتوى يحتاج JavaScript؛ المعاينة المحفوظة متاحة أدناه. لا يتم نشر أي شيء
                    تلقائياً.</noscript>
                <details class="mt-4 rounded-xl border p-4">
                    <summary>معاينة التعديلات غير المحفوظة</summary>
                    <div class="education-preview mt-4 space-y-4" data-education-preview></div>
                </details>
            </section>
            @foreach (['product' => 'المنتجات المرتبطة', 'concern' => 'الاهتمامات المرتبطة'] as $kind => $label)
                <fieldset>
                    <legend class="font-bold">{{ $label }} — اختيارية، حتى20</legend>
                    <p class="my-2 text-sm">الرابط المخفي يحجب من العرض العام دون إخفاء المقال؛ الاحتفاظ بالمحدد سابقاً
                        مسموح.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($choices[$kind] as $choice)
                            @php($selectedLink = collect($draft[$kind . 's'])->firstWhere('id', $choice->id))
                            <div class="min-w-0 rounded-xl border p-3"><label class="flex items-start gap-3"><input
                                        type="checkbox" name="{{ $kind }}s[{{ $choice->id }}][selected]"
                                        value="1" @checked($selectedLink)><span
                                        class="break-words">{{ $choice->name }}</span></label><label>ترتيب الرابط<input
                                        type="number" min="0" max="999999999" required
                                        name="{{ $kind }}s[{{ $choice->id }}][sort_order]"
                                        value="{{ $selectedLink['sort_order'] ?? 0 }}"></label></div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <section>
                <h2 class="font-bold">المصادر التحريرية</h2>
                <p class="my-3 text-sm">حتى10مصادر تختارينها بنفسك؛ المحتوى الصحي يحتاج مصدراً على الأقل. قبول الرابط لا
                    يعني التحقق من صحته أو موثوقيته.</p>
                <div class="space-y-3" data-education-sources>
                    @foreach ($draft['sources'] as $index => $source)
                        @include('owner.education.source')
                    @endforeach
                </div>
                <template data-education-source-template>@include('owner.education.source', ['index' => '__INDEX__', 'source' => []])</template>
                <button type="button" data-education-source-add disabled>إضافة مرجع</button>
            </section>
            <section>
                <h2 class="font-bold">صور المسودة</h2>
                <p class="my-3 text-sm">أبقي الصور المطلوبة فقط. الصورة الداخلية المحتفظ بها يجب وضعها في المتن مرة واحدة
                    قبل النشر. إزالة صورتها تحتاج إزالة كتلتها أيضاً.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($draft['images'] as $i => $im)
                        <fieldset class="min-w-0 rounded-xl border p-4" data-education-image
                            data-media-id="{{ $im['media_id'] }}" data-role="{{ $im['role'] }}"
                            data-preview-url="{{ route('owner.education.media', ['id' => $im['media_id']]) }}">
                            <legend>{{ $im['role'] === 'cover' ? 'صورة الغلاف' : 'صورة داخلية ' . ($i + 1) }}</legend><img
                                class="my-3 h-40 w-full object-contain"
                                src="{{ route('owner.education.media', ['id' => $im['media_id']]) }}"
                                alt="{{ $im['alt_text'] }}">
                            <input type="hidden" name="images[{{ $i }}][media_id]"
                                value="{{ $im['media_id'] }}"><input type="hidden"
                                name="images[{{ $i }}][role]" value="{{ $im['role'] }}">
                            <label class="flex items-center gap-3"><input type="checkbox"
                                    name="images[{{ $i }}][keep]" value="1" checked>الاحتفاظ بالصورة في
                                المسودة</label>
                            <label>الوصف البديل<input name="images[{{ $i }}][alt_text]" required
                                    maxlength="250" value="{{ $im['alt_text'] }}" data-image-alt></label>
                        </fieldset>
                    @endforeach
                </div>
            </section>
            <button class="bg-emerald-900 text-white">حفظ المسودة</button>
        </form>
        @if ($record)
            <form method="POST" enctype="multipart/form-data" action="{{ $endpoint }}"
                class="education-panel mt-6 rounded-2xl border bg-white p-5">
                @include('owner.education.command', [
                    'version' => $record->lock_version,
                    'action' => 'media',
                ])
                <h2 class="font-bold">رفع صورة خاصة</h2>
                <p class="my-3 text-sm">احفظي التعديلات أولاً. غلاف واحد وحتى5صور داخلية؛ كل رفع يصنع إصدار مسودة دون تغيير
                    المنشور.</p>
                <label>استخدام الصورة<select name="role">
                        <option value="inline">صورة داخل المقال</option>
                        <option value="cover">صورة غلاف</option>
                    </select></label>
                <label>الصورة (JPEG/PNG/WebP/GIF حتى5MiB)<input type="file" name="image" required
                        accept="image/png,image/jpeg,image/webp,image/gif"></label>
                <label>الوصف البديل<input name="alt_text" required maxlength="250"></label><button>رفع الصورة</button>
            </form>
        @endif
    @endif
    @if ($record)
        <section class="education-panel mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ($record->archived_at ? ['restore' => 'استعادة غير منشورة'] : ['publish' => 'نشر النسخة الحالية', 'unpublish' => 'إلغاء النشر', 'archive' => 'أرشفة المقال'] as $action => $label)
                <form method="POST" action="{{ $endpoint }}" class="rounded-2xl border bg-white p-5"
                    data-education-confirm="{{ $label }}">
                    @include('owner.education.command', [
                        'version' => $record->lock_version,
                        'action' => $action,
                    ])
                    @if ($action === 'publish')
                        <label class="flex gap-3"><input type="checkbox" name="review_acknowledged" value="1"
                                required>أقر بأنني راجعت المحتوى الذي سيُنشر.</label>
                        @if ($draft['editorial_scope'] === 'health_related')
                            <label class="mt-3 flex gap-3"><input type="checkbox" name="health_acknowledged"
                                    value="1" required>أقر بمراجعة الادعاءات الصحية ومصادرها؛ النظام لا يمنح اعتماداً
                                طبياً.</label>
                        @endif
                    @endif
                    <label class="mt-3 flex gap-3"><input type="checkbox" name="confirm" value="1" required>أؤكد
                        {{ $label }}</label><button>{{ $label }}</button>
                </form>
            @endforeach
        </section>
        <dialog data-education-confirmation aria-labelledby="education-confirm-title"
            class="m-auto w-[calc(100%_-_2rem)] max-w-lg rounded-2xl border bg-white p-6 backdrop:bg-stone-950/40">
            <h2 id="education-confirm-title" class="font-bold">تأكيد إجراء المقال</h2>
            <p class="my-4" data-education-confirm-label></p>
            <p>يتغير العرض العام عند نجاح الإجراء. حفظ مسودة وحده لا ينشرها.</p>
            <div class="education-panel mt-4 flex flex-wrap gap-3"><button type="button" data-education-cancel
                    autofocus>العودة للمراجعة</button><button type="button" data-education-accept>تأكيد التنفيذ</button>
            </div>
        </dialog>
        <section class="mt-6 rounded-2xl border bg-white p-5">
            <h2 class="mb-4 font-bold">معاينة المسودة المحفوظة — خاصة</h2>@include('owner.education.preview', ['preview' => $draft])
        </section>
        @if ($published)
            <section class="mt-6 rounded-2xl border bg-white p-5">
                <h2 class="mb-4 font-bold">النسخة المنشورة المحفوظة {{ $published['version'] }}</h2>
                @include('owner.education.preview', ['preview' => $published])
            </section>
        @endif
        <section class="mt-6 rounded-2xl border bg-white p-5">
            <h2 class="font-bold">الإصدارات</h2>
            <ul class="my-4 space-y-3">
                @foreach ($history as $h)
                    <li><a class="underline"
                            href="{{ route('owner.education.edit', ['id' => $record->id, 'revision' => (string) $h->version]) }}">إصدار{{ $h->version }}
                            · {{ $h->action }} · {{ $h->display_name }} · {{ $h->created_at }}</a></li>
                @endforeach
            </ul>{{ $history->withQueryString()->links() }}
        </section>
        @if ($snapshot)
            <section class="mt-6 rounded-2xl border bg-white p-5">
                <h2 class="mb-4 font-bold">لقطة تاريخية خاصة — {{ $snapshot['version'] }}</h2>
                @include('owner.education.preview', ['preview' => $snapshot])
            </section>
        @endif
    @endif
@endsection
