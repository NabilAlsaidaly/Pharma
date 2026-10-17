@extends('owner.layout')
@section('title', $worker ? 'تحرير جهة توصيل' : 'إضافة عامل توصيل')
@section('content')
    <x-owner-page-heading :title="$worker ? 'تحرير جهة توصيل' : 'إضافة عامل توصيل'" description="بيانات تواصل داخلية فقط. صحة صيغة الهاتف لا تعني أنه موثق. لا حساب سائق أو إسناد طلب أو إرسال رسائل." />
    <a class="mb-6 inline-block text-sm underline" href="{{ route('owner.delivery.index') }}">العودة إلى التوصيل</a>
    @if($worker)<a class="mb-6 ms-5 inline-block text-sm underline" href="{{ route('owner.delivery.history', ['worker' => $worker->id]) }}">سجل إجراءات هذا العامل</a><p class="mb-4 text-sm">الإصدار الحالي: {{ $worker->lock_version }}</p>@endif
    @php($endpoint = route('owner.delivery.mutate', $worker ? ['id' => $worker->id] : []))
    <form method="POST" action="{{ $endpoint }}" class="mb-6 space-y-5 rounded-xl border bg-white p-5 sm:p-7">
        @csrf
        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid7() }}">
        <input type="hidden" name="expected_version" value="{{ $worker->lock_version ?? '0' }}">
        <input type="hidden" name="action" value="save">
        <label class="block text-sm" for="worker-name">اسم العامل<input id="worker-name" name="values[name]" value="{{ $worker->name ?? '' }}" maxlength="150" required class="mt-2 block w-full rounded-lg border p-3"></label>
        <label class="block text-sm" for="worker-phone">الهاتف<input id="worker-phone" name="values[phone]" value="{{ $worker->phone ?? '' }}" maxlength="40" type="tel" dir="ltr" required class="mt-2 block w-full rounded-lg border p-3"></label>
        <label class="block text-sm" for="worker-notes">ملاحظة داخلية اختيارية<textarea id="worker-notes" name="values[notes]" maxlength="1000" rows="4" class="mt-2 block w-full rounded-lg border p-3">{{ $worker->notes ?? '' }}</textarea></label>
        <label class="block text-sm" for="worker-state">الحالة<select id="worker-state" name="values[is_active]" required class="mt-2 block rounded-lg border p-3"><option value="1" @selected(!$worker || $worker->is_active)>نشط</option><option value="0" @selected($worker && !$worker->is_active)>غير نشط</option></select></label>
        <button class="rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white">حفظ جهة التواصل</button>
    </form>
    @if($worker)
        <form method="POST" action="{{ $endpoint }}" class="rounded-xl border bg-white p-5">
            @csrf<input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid7() }}"><input type="hidden" name="expected_version" value="{{ $worker->lock_version }}"><input type="hidden" name="action" value="{{ $worker->is_active ? 'disable' : 'enable' }}">
            <label class="mb-5 flex items-start gap-3 text-sm leading-7"><input type="checkbox" name="confirm" value="1" required class="mt-2">أعتمد {{ $worker->is_active ? 'تعطيل' : 'إعادة تفعيل' }} جهة التواصل، مع حفظ هويتها وسجلها.</label><button class="rounded-lg border px-5 py-3 text-sm">{{ $worker->is_active ? 'تعطيل العامل' : 'إعادة التفعيل' }}</button>
        </form>
    @endif
@endsection
