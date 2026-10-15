@extends('owner.layout')
@section('title', 'راجعي بيانات الكتالوج')
@section('content')
<x-owner-page-heading title="تعذر حفظ الإجراء" description="لم تُعتمد العملية. عودي إلى النموذج لمراجعة البيانات، وحدّثي الصفحة عند وجود تعارض في الإصدار." />
<div role="alert" class="rounded-2xl border border-red-200 bg-white p-6"><ul class="list-inside list-disc space-y-3 text-sm text-red-900">@foreach($validationErrors as $messages)@foreach($messages as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul></div>
<button type="button" onclick="history.back()" class="mt-5 rounded-lg border border-stone-300 bg-white px-5 py-3">العودة إلى النموذج</button>
@endsection
