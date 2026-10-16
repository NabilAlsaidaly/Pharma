@extends('owner.layout')
@section('title', 'تحرير مناسبة')
@section('content')
    <x-owner-page-heading title="تحرير مناسبة"
        description="الأرشفة تحفظ تاريخ الطلبات؛ الاستعادة لا تفعّل المناسبة تلقائياً." />
    @include('owner.gifts.tabs')
    @php($endpoint = route('owner.gifts.occasion.mutate', $record ? ['id' => $record->id] : []))
    @if (!$record || !$record->archived_at)
        <form method="POST" action="{{ $endpoint }}" class="space-y-5 rounded-2xl border bg-white p-5">
            @include('owner.gifts.command', [
                'version' => $record->lock_version ?? '0',
                'action' => 'save',
            ])
            <label class="block">اسم المناسبة<input name="values[name]" required maxlength="160"
                    value="{{ $record->name ?? '' }}" class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="block">الوصف الاختياري
                <textarea name="values[description]" maxlength="500" class="mt-2 block w-full rounded-xl border p-3">{{ $record->description ?? '' }}</textarea>
            </label>
            <label class="block">الترتيب<input name="values[sort_order]" required type="number" min="0"
                    max="999999999" value="{{ $record->sort_order ?? 0 }}"
                    class="mt-2 block w-full rounded-xl border p-3"></label>
            <label class="block">الإظهار<select name="values[is_active]" class="mt-2 block w-full rounded-xl border p-3">
                    <option value="0" @selected(!($record->is_active ?? false))>مخفية</option>
                    <option value="1" @selected($record->is_active ?? false)>متاحة</option>
                </select></label>
            <button class="rounded-xl bg-emerald-900 px-5 py-3 text-white">حفظ المناسبة</button>
        </form>
    @endif
    @if ($record)
        <form method="POST" action="{{ $endpoint }}" class="mt-6 rounded-2xl border bg-white p-5">
            @include('owner.gifts.command', [
                'version' => $record->lock_version,
                'action' => $record->archived_at ? 'restore' : 'archive',
            ])<label class="flex gap-3"><input type="checkbox" required name="confirm"
                    value="1"><span>أؤكد
                    {{ $record->archived_at ? 'الاستعادة كمناسبة مخفية' : 'أرشفة المناسبة ومنع اختيارها في طلب جديد' }}</span></label><button
                class="mt-4 rounded-xl border px-5 py-3">{{ $record->archived_at ? 'استعادة' : 'أرشفة' }}</button></form>
    @endif
@endsection
