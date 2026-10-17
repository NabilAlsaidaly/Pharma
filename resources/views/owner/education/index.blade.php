@extends('owner.layout')
@section('title', 'المقالات والنصائح')
@section('content')
    <x-owner-page-heading title="المقالات والنصائح"
        description="تديرين ما يُكتب ويُنشر. النظام لا يولّد نصائح ولا يتحقق من صحتها الطبية." />
    @include('owner.education.tabs')
    <form method="GET" class="education-panel mb-6 rounded-2xl border bg-white p-5">
        <label>بحث في عنوان المسودة<input name="q" maxlength="100" value="{{ request('q') }}"></label>
        <label>الحالة<select name="state">
                <option value="">كل الحالات</option>
                @foreach (['published' => 'منشور', 'draft' => 'غير منشور', 'archived' => 'مؤرشف'] as $key => $label)
                    <option value="{{ $key }}" @selected(request('state') === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
        <button>تصفية المقالات</button>
    </form>
    <div class="space-y-4">
        @forelse($records as $r)
            <article class="rounded-2xl border bg-white p-5 min-w-0">
                <h2 class="break-words font-bold"><a class="underline"
                        href="{{ route('owner.education.edit', ['id' => $r->id]) }}">{{ $r->title }}</a></h2>
                <p class="mt-3 break-all">{{ $r->slug }}</p>
                <p class="mt-2">
                    {{ $r->archived_at ? 'مؤرشف' : ($r->published_version ? 'منشور بالإصدار ' . $r->published_version : 'مسودة غير منشورة') }}
                    · المسودة {{ $r->lock_version }}</p>
            </article>
        @empty<p class="rounded-2xl border bg-white p-5">لا توجد مقالات مطابقة. يمكنك إنشاء مسودة جديدة.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $records->withQueryString()->links() }}</div>
@endsection
