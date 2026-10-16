@extends('owner.layout')
@section('title', 'باقات الهدايا')
@section('content')
    <x-owner-page-heading title="باقات الهدايا" description="باقات جاهزة بسعر مستقل؛ الطلبات مبدئية ولا تحجز مخزوناً." />
    @include('owner.gifts.tabs')
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($records as $record)
            <article class="min-w-0 rounded-2xl border bg-white p-5">
                <h2 class="break-words font-bold"><a
                        href="{{ route('owner.gifts.edit', ['id' => $record->id]) }}">{{ $record->name }}</a></h2>
                <p class="mt-2 text-sm">
                    {{ $record->archived_at ? 'مؤرشف' : ($record->published_version ? 'له نسخة منشورة' : 'غير منشور') }}</p>
                <p class="mt-2 break-all text-sm" dir="ltr">USD {{ $record->price_usd ?? '—' }} · SYP
                    {{ $record->price_syp ?? '—' }}</p>
                <p class="mt-2 text-sm">المسودة {{ $record->lock_version }} / المنشور {{ $record->published_version ?? '—' }}
                </p>
            </article>
        @empty<p class="rounded-2xl border bg-white p-6">لم تُنشأ باقات بعد؛ لا توجد أسعار أو مكونات افتراضية.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
