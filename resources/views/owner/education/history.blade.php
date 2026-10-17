@extends('owner.layout')
@section('title', 'سجل المحتوى التعليمي')
@section('content')
    <x-owner-page-heading title="سجل المحتوى التعليمي"
        description="سجل خاص لإجراءات المقالات والفئات دون نسخ متن المقال في التدقيق العام." />
    @include('owner.education.tabs')
    <div class="space-y-4">
        @forelse($records as $r)
            <article class="rounded-2xl border bg-white p-5">
                <p class="break-words">{{ $r->display_name }} · {{ $r->target_type }} · {{ $r->action }}</p>
                <p class="mt-3 break-all"><a class="underline"
                        href="{{ route($r->target_type === 'article' ? 'owner.education.edit' : 'owner.education.category.edit', ['id' => $r->target_id]) }}">{{ $r->target_id }}</a>
                </p>
                <p class="mt-3">إصدار{{ $r->previous_version }} ← {{ $r->new_version }} · {{ $r->created_at }}</p>
        </article>@empty<p>لا توجد تعديلات محفوظة.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
