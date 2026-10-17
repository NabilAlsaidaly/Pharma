@extends('owner.layout')
@section('title', 'سجل إجراءات عمال التوصيل')
@section('content')
    <x-owner-page-heading title="سجل إجراءات عمال التوصيل" description="سجل خاص للإجراء والإصدار وأسماء الحقول المتغيرة، دون نسخ أرقام الهواتف أو الملاحظات القديمة." />
    <a class="mb-6 inline-block text-sm underline" href="{{ route('owner.delivery.index') }}">العودة إلى التوصيل</a>
    <ul class="space-y-3">
        @forelse($events as $event)
            <li class="min-w-0 rounded-xl border bg-white p-5 text-sm leading-7"><a class="break-all underline" href="{{ route('owner.delivery.edit', ['id' => $event->worker_id]) }}">جهة التواصل {{ $event->worker_id }}</a><p>{{ ['save' => 'حفظ', 'disable' => 'تعطيل', 'enable' => 'إعادة تفعيل'][$event->action] }} · {{ $event->display_name }} · الإصدار {{ $event->previous_version }} ← {{ $event->new_version }}</p><time dir="ltr">{{ $event->created_at }}</time><p class="break-words">الحقول: {{ implode('، ', json_decode($event->changed_fields, true)) ?: 'لا تغير في القيم' }}</p></li>
        @empty<li class="rounded-xl border bg-white p-5 text-sm">لا إجراءات مسجلة.</li>
        @endforelse
    </ul><x-owner-pagination :paginator="$events" />
@endsection
