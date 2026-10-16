@extends('owner.layout')
@section('title', 'مناسبات الهدايا')
@section('content')
    <x-owner-page-heading title="مناسبات الهدايا" description="مناسبات اختيارية تديرينها؛ لا تضيف سعراً أو موعد تسليم." />
    @include('owner.gifts.tabs')
    <a class="mb-5 inline-block rounded-xl bg-emerald-900 px-5 py-3 text-white"
        href="{{ route('owner.gifts.occasion.create') }}">إضافة مناسبة</a>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($records as $record)
            <article class="min-w-0 rounded-2xl border bg-white p-5">
                <h2 class="break-words font-bold"><a
                        href="{{ route('owner.gifts.occasion.edit', ['id' => $record->id]) }}">{{ $record->name }}</a></h2>
                <p class="mt-3">
                    {{ $record->archived_at ? 'مؤرشفة' : ($record->is_active ? 'متاحة للاختيار عند ربطها بباقة منشورة' : 'مخفية') }}
                </p>
        </article>@empty<p>لا مناسبات بعد.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
