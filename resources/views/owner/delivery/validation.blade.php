@extends('owner.layout')
@section('title', 'مراجعة بيانات التوصيل')
@section('content')
    <x-owner-page-heading title="يرجى مراجعة بيانات التوصيل" description="لم يُحفَظ هذا الإجراء. حدّثي السجل وراجعي البيانات قبل إعادة المحاولة." />
    <ul class="mb-6 space-y-3 rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm leading-7 text-rose-800">
        @foreach($validationErrors as $messages)
            @foreach((array) $messages as $message)<li class="break-words">{{ $message }}</li>@endforeach
        @endforeach
    </ul>
    <a class="inline-block rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white" href="{{ route('owner.delivery.index') }}">العودة إلى التوصيل</a>
@endsection
