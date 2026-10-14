@extends('owner.layout')
@section('title', 'مراجعة خيارات العرض')
@section('content')
    <x-owner-page-heading title="يرجى مراجعة خيارات العرض"
        description="لم تُنفّذ عملية البحث بهذه الخيارات. اختاري قيماً صالحة ثم حاولي مجدداً." />
    <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-red-900">
        <ul class="list-inside list-disc space-y-2">
            @foreach ($validationErrors as $messages)
                @foreach ($messages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    </div><a href="{{ route('owner.dashboard') }}"
        class="mt-6 inline-block rounded-xl bg-emerald-900 px-5 py-3 text-sm font-semibold text-white">العودة إلى نظرة
        عامة</a>
@endsection
