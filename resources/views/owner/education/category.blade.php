@extends('owner.layout')
@section('title', 'تحرير فئة المقال')
@section('content')
    <x-owner-page-heading title="فئة المقال" description="الرابط دائم؛ الاستعادة لا تجعل الفئة ظاهرة تلقائياً." />
    @include('owner.education.tabs')
    @php($endpoint = route('owner.education.category.mutate', $record ? ['id' => $record->id] : []))
    @if (!$record || !$record->archived_at)
        <form class="education-panel space-y-4 rounded-2xl border bg-white p-5" method="POST" action="{{ $endpoint }}">
            @include('owner.education.command', [
                'version' => $record->lock_version ?? '0',
                'action' => 'save',
            ])
            <label>اسم الفئة<input name="values[name]" required maxlength="160" value="{{ $record->name ?? '' }}"></label>
            @if (!$record)
                <label>رابط دائم<input name="slug" required maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                    dir="ltr"></label>@else<p class="break-all">{{ $record->slug }}</p>
            @endif
            <label>وصف اختياري
                <textarea name="values[description]" maxlength="500">{{ $record->description ?? '' }}</textarea>
            </label>
            <label>الترتيب<input name="values[sort_order]" required type="number" min="0" max="999999999"
                    value="{{ $record->sort_order ?? 0 }}"></label>
            <label class="flex items-center gap-3"><input type="checkbox" name="values[is_active]" value="1"
                    @checked($record->is_active ?? false)>إظهار الفئة للعامة</label><button>حفظ الفئة</button>
        </form>
    @endif
    @if ($record)
        <form class="education-panel mt-6 rounded-2xl border bg-white p-5" method="POST" action="{{ $endpoint }}">
            @include('owner.education.command', [
                'version' => $record->lock_version,
                'action' => $record->archived_at ? 'restore' : 'archive',
            ])<label class="flex gap-3"><input type="checkbox" name="confirm" value="1"
                    required>أؤكد
                {{ $record->archived_at ? 'الاستعادة مخفية' : 'أرشفة الفئة' }}</label><button>{{ $record->archived_at ? 'استعادة مخفية' : 'أرشفة الفئة' }}</button>
        </form>
    @endif
    <section class="mt-6 rounded-2xl border bg-white p-5">
        <h2 class="font-bold">تاريخ الفئة الخاص</h2>
        <ul class="my-3 space-y-3">
            @foreach ($history as $h)
                <li class="break-words">إصدار{{ $h->version }} · {{ $h->action }} · {{ $h->display_name }} ·
                    {{ $h->created_at }}<p>{{ $h->name }} — {{ $h->description }} ·
                        {{ $h->is_active ? 'ظاهرة' : 'مخفية' }}</p>
                </li>
            @endforeach
        </ul>{{ $history->withQueryString()->links() }}
    </section>
@endsection
