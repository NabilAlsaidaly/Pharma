@extends('owner.layout')
@section('title', 'طلبات الهدايا')
@section('content')
    <x-owner-page-heading title="طلبات الهدايا"
        description="طلبات مبدئية؛ المراجعة لا تؤكد شراءً أو دفعاً أو شحناً. بيانات المستلم تظهر على التفاصيل المأذونة فقط." />
    @include('owner.gifts.tabs')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border bg-white p-5 sm:grid-cols-2 lg:grid-cols-3">
        <label>معرّف الطلب<input name="q" maxlength="36" value="{{ $filters['q'] ?? '' }}"
                class="mt-2 block w-full rounded-xl border p-3" dir="ltr"></label>
        <label>حالة المراجعة<select name="review_status" class="mt-2 block w-full rounded-xl border p-3">
                <option value="">جميع الحالات</option>
                @foreach (\App\Domains\Orders\Domain\Review\ReviewStatus::cases() as $state)
                    <option value="{{ $state->value }}" @selected(($filters['review_status'] ?? '') === $state->value)>{{ $state->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>العملة<select name="currency" class="mt-2 block w-full rounded-xl border p-3">
                <option value="">العملتان</option>
                @foreach (['USD', 'SYP'] as $currency)
                    <option @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </label>
        <label>من تاريخ<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                class="mt-2 block w-full rounded-xl border p-3"></label><label>إلى تاريخ (حتى 93 يوماً)<input type="date"
                name="to" value="{{ $filters['to'] ?? '' }}"
                class="mt-2 block w-full rounded-xl border p-3"></label><button
            class="self-end rounded-xl border px-5 py-3">تصفية</button>
    </form>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse($records as $record)
            <article class="min-w-0 rounded-2xl border bg-white p-5">
                <h2 class="break-words font-bold">{{ $record->package_name }}</h2><a
                    class="mt-3 block break-all text-sm underline"
                    href="{{ route('owner.gifts.requests.show', ['id' => $record->id]) }}">{{ $record->id }}</a>
                <p class="mt-3">
                    {{ \App\Domains\Orders\Domain\Review\ReviewStatus::from($record->review_status)->label() }} ·
                    {{ $record->currency }}</p>
                <p class="mt-2">السعر المعروض تاريخياً: {{ $record->display_price_snapshot }} (غير ملزم)</p>
                <p class="mt-2 text-sm">{{ $record->created_at }}</p>
        </article>@empty<p>لا طلبات مطابقة.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
