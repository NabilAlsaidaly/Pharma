@extends('owner.layout')
@section('title', 'مراجعة بيانات المتجر')
@section('content')
<x-owner-page-heading title="يرجى مراجعة بيانات المتجر" description="لم يُحفظ هذا الإجراء. راجعي البيانات أو حدّثي الصفحة لعرض أحدث إصدار، ثم أعيدي المحاولة." />
<section role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm leading-7 text-rose-800">
    <ul class="list-inside list-disc">
        @foreach($validationErrors as $messages)
            @foreach($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        @endforeach
    </ul>
</section>
<a class="inline-block rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white" href="{{ route('owner.settings.index') }}">العودة إلى إعدادات المتجر</a>
@endsection
