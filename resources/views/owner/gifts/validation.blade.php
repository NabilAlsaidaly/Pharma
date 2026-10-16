@extends('owner.layout')
@section('title', 'مراجعة بيانات الهدايا')
@section('content')
    <x-owner-page-heading title="مراجعة بيانات الهدايا"
        description="لم يُحفظ الإجراء المرفوض. راجعي القيم أو حدّثي الصفحة عند تعارض الإصدار." />
    @include('owner.gifts.tabs')
    <section role="alert" class="rounded-2xl border bg-white p-5">
        <ul class="space-y-3">
            @foreach ($validationErrors as $messages)
                @foreach ((array) $messages as $error)
                    <li class="break-words">{{ $error }}</li>
                @endforeach
            @endforeach
        </ul>
    </section>
@endsection
