@extends('owner.layout')
@section('title', 'سجل تغييرات الهدايا')
@section('content')
    <x-owner-page-heading title="سجل تغييرات الهدايا"
        description="أحداث منسوبة للمالكة دون نسخ بيانات المستلمين أو نصوص الرسائل والملاحظات." />
    @include('owner.gifts.tabs')
    <div class="space-y-4">
        @forelse($records as $event)
            <article class="min-w-0 rounded-2xl border bg-white p-5">
                <p class="font-bold">{{ $event->actor_name }}</p>
                <p class="mt-2 break-all">{{ $event->target_type }} · {{ $event->target_id }}</p>
                <p class="mt-2">{{ $event->action }} · {{ $event->previous_version }} → {{ $event->new_version }} ·
                    {{ $event->created_at }}</p>
        </article>@empty<p>لا أحداث بعد.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
