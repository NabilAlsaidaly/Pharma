@props(['title', 'text', 'description' => null, 'badge' => 'بانتظار الربط'])
<section aria-label="{{ $title }}" class="mt-6 flex flex-wrap items-center gap-5 rounded-2xl border border-dashed border-stone-300 bg-stone-100/60 p-6">
    <span class="rounded-xl bg-white p-3 text-stone-500"><x-owner-icon name="chart" /></span><div class="flex-1"><h2 class="font-semibold">{{ $title }}</h2><p class="mt-2 text-sm text-stone-600">{{ $text }}</p>@if($description)<p class="mt-2 text-xs leading-6 text-stone-500">{{ $description }}</p>@endif</div><span class="rounded-full bg-white px-3 py-1 text-xs text-stone-600">{{ $badge }}</span>
</section>
