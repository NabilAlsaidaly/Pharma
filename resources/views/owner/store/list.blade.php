@extends('owner.layout')
@section('title', $spec['label'])
@section('content')
    <x-owner-page-heading :title="$spec['label']"
        description="المسودات والأرشيف لا يظهران للعملاء. تُحفظ الإصدارات السابقة في السجل دون حذف." />
    @include('owner.store.tabs')
    <a class="mb-5 inline-block rounded-lg bg-emerald-900 px-5 py-3 text-sm text-white"
        href="{{ route('owner.' . $section . '.create', [$section === 'settings' ? 'group' : 'type' => $type]) }}">إضافة
        {{ $section === 'settings' ? 'منطقة' : 'محتوى' }}</a>
    <div class="space-y-3">
        @forelse($records as $item)
            <a class="block min-w-0 rounded-xl border bg-white p-5"
                href="{{ route('owner.' . $section . '.edit', [$section === 'settings' ? 'group' : 'type' => $type, 'id' => $item->id]) }}">
                <h2 class="break-words font-semibold">
                    @if(($spec['media'] ?? false) && $item->image_path)<img class="mb-4 h-24 max-w-full rounded-lg object-contain" src="{{ \App\Support\BusinessRaster::previewUrl($item->image_path) }}" alt="معاينة صورة {{ $item->title ?? $item->name ?? '' }}">@endif
                    {{ $item->name ?? ($item->title ?? ($item->question ?? ($item->mode ?? ($item->first_currency ?? $item->id)))) }}
                </h2>
                <p class="mt-2 text-xs text-stone-600">
                    {{ $item->archived_at ? 'مؤرشف' : ($item->published_version ? 'منشور — قد توجد مسودة أحدث' : ($spec['public'] ? 'مسودة غير منشورة' : 'محفوظ للتجهيز — بانتظار الربط')) }}
                    · الإصدار {{ $item->lock_version }}</p>
                @if($type === 'zones')<p class="mt-3 text-sm">{{ $item->governorate }} {{ $item->city }} · رسوم تحضيرية فقط</p><p class="mt-2 text-xs" dir="ltr">USD: {{ $item->fee_usd ?? 'غير محدد' }} · SYP: {{ $item->fee_syp ?? 'غير محدد' }}</p>@endif
            </a>
        @empty<p class="rounded-xl border bg-white p-6 text-sm">لم تُضاف بيانات بعد. لا توجد قيم افتراضية منشورة.</p>
        @endforelse
    </div><x-owner-pagination :paginator="$records" />
@endsection
