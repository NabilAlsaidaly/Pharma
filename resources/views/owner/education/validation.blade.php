@extends('owner.layout')
@section('title', 'مراجعة بيانات المحتوى التعليمي')
@section('content')
    <x-owner-page-heading title="تعذر حفظ الإجراء"
        description="راجعي البيانات أو حدّثي الصفحة عند تعارض الإصدار. لم تُنشر تعديلات غير مكتملة." />
    @include('owner.education.tabs')
    <section role="alert" class="rounded-2xl border border-amber-300 bg-white p-5">
        <ul class="space-y-3">
            @foreach ($validationErrors as $messages)
                @foreach ($messages as $message)
                    <li class="break-words">{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
        <p class="mt-5">لم يُنسخ متن المقال أو الملفات إلى الجلسة؛ ارجعي إلى النموذج للمراجعة قبل تحديثه.</p>
    </section>
@endsection
