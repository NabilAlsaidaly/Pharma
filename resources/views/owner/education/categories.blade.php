@extends('owner.layout')
@section('title', 'تصنيفات المقالات')
@section('content')
    <x-owner-page-heading title="تصنيفات المقالات" description="فئات تحريرية مستقلة عن اهتمامات الكتالوج؛ إخفاء الفئة لا يلغي نشر المقال." />
    @include('owner.education.tabs')
    <a class="mb-5 inline-block rounded-xl border bg-white px-5 py-3" href="{{ route('owner.education.category.create') }}">إضافة فئة</a>
    <div class="space-y-4">@forelse($records as $r)<article class="rounded-2xl border bg-white p-5"><h2 class="break-words font-bold"><a class="underline" href="{{ route('owner.education.category.edit', ['id' => $r->id]) }}">{{ $r->name }}</a></h2><p class="mt-3">{{ $r->archived_at ? 'مؤرشفة' : ($r->is_active ? 'ظاهرة' : 'مخفية') }} · إصدار{{ $r->lock_version }}</p></article>@empty<p>لا توجد فئات. المقال يمكن أن يُنشر دون فئة.</p>@endforelse</div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
