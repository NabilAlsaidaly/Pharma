<div class="education-preview space-y-4 break-words">
    <h2 class="text-xl font-bold">{{ $preview['title'] }}</h2>
    @foreach ($preview['images'] as $im)
        @if ($im['role'] === 'cover')
            <img class="max-h-96 max-w-full object-contain"
                src="{{ route('owner.education.media', ['id' => $im['media_id']]) }}" alt="{{ $im['alt_text'] }}">
        @endif
    @endforeach
    @foreach ($preview['body']['blocks'] as $b)
        @if ($b['type'] === 'paragraph')
            <p class="whitespace-pre-wrap">@include('owner.education.runs', ['runs' => $b['runs']])</p>
        @elseif($b['type'] === 'heading')
            @if ($b['level'] === 2)
            <h2 class="text-xl font-bold whitespace-pre-wrap">@include('owner.education.runs', ['runs' => $b['runs']])</h2>@else<h3
                    class="text-lg font-bold whitespace-pre-wrap">@include('owner.education.runs', ['runs' => $b['runs']])</h3>
            @endif
        @elseif($b['type'] === 'list')
            @if ($b['style'] === 'ordered')
            <ol class="list-decimal ps-6">@else<ul class="list-disc ps-6">
            @endif
            @foreach ($b['items'] as $runs)
                <li class="whitespace-pre-wrap">@include('owner.education.runs')</li>
            @endforeach
            @if ($b['style'] === 'ordered')
            </ol>@else</ul>
            @endif
        @elseif($b['type'] === 'image')
            @php($image = collect($preview['images'])->firstWhere('media_id', $b['media_id']))
            @if ($image)
                <figure><img class="max-h-96 max-w-full object-contain"
                        src="{{ route('owner.education.media', ['id' => $image['media_id']]) }}"
                        alt="{{ $image['alt_text'] }}">
                    @if ($b['caption'])
                        <figcaption class="mt-2 whitespace-pre-wrap">{{ $b['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
        @endif
    @endforeach
    <p class="text-sm">الفئة المحفوظة: {{ $preview['category_name'] ?? 'دون فئة' }} · نطاق المراجعة:
        {{ $preview['editorial_scope'] ?? 'غير محدد' }}</p>
    @if (isset($preview['review_acknowledged']))
        <p class="text-sm">إقرار مراجعة هذا الإصدار:
            {{ $preview['review_acknowledged'] ? 'مسجل' : 'لم يسجل لهذا الإجراء' }} · إقرار مراجعة الادعاءات الصحية:
            {{ $preview['health_acknowledged'] ? 'مسجل' : 'غير مسجل لهذا الإجراء' }}</p>
    @endif
    @foreach ($preview['sources'] as $source)
        <p class="break-words"><a class="underline" href="{{ $source['url'] }}"
                rel="noopener noreferrer">{{ $source['title'] }}</a> {{ $source['note'] }}</p>
    @endforeach
</div>
