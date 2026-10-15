@props(['title', 'description' => null])
<div class="mb-7">
    <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-800">سمية فارما · مساحة المالكة</p>
    <h1 class="text-2xl font-bold sm:text-3xl">{{ $title }}</h1>
    @if ($description)
        <p class="mt-3 max-w-3xl text-sm leading-7 text-stone-600">{{ $description }}</p>
    @endif
</div>
