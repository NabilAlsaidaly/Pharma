@extends('owner.layout')
@section('title', 'سجل إعدادات المتجر والنشر')
@section('content')
    <x-owner-page-heading title="سجل التغييرات والنشر"
        description="سجل تراكمي يوضح من غيّر البيانات ومتى، دون نسخ النصوص الطويلة أو الأسرار." />
    @include('owner.store.tabs')
    <ol class="space-y-4">
        @forelse($events as $event)
            <li class="min-w-0 rounded-xl border bg-white p-5 text-sm"><strong>{{ $event->display_name }} ·
                    {{ ['save' => 'حفظ مسودة', 'media' => 'حفظ صورة', 'publish' => 'نشر', 'unpublish' => 'إلغاء نشر', 'archive' => 'أرشفة', 'restore' => 'استعادة'][$event->action] }}</strong>
                <p class="mt-3">الإصدار {{ $event->previous_version }} ← {{ $event->new_version }}</p>
                <p class="mt-2 text-xs">{{ collect(array_merge(\App\Domains\Settings\Application\SettingGroups::all(), \App\Domains\Content\Application\ContentTypes::all()))->firstWhere('table', $event->target_type)['label'] }}</p>
                <p class="mt-2 break-all text-xs" dir="ltr">{{ $event->target_id }}</p><time
                    class="mt-3 block text-xs text-stone-500" dir="ltr">{{ $event->created_at }}</time>
        </li>@empty<li class="rounded-xl border bg-white p-6 text-sm">لا توجد تغييرات مسجلة.</li>
        @endforelse
    </ol><x-owner-pagination :paginator="$events" />
@endsection
