@props(['value'])
@php(
    $label = match ($value) {
        'active' => 'نشط',
        'hidden' => 'مخفي',
        'submitted' => 'مسجّل',
        'available' => 'متاح',
        'on_request' => 'عند الطلب',
        'unavailable' => 'غير متاح',
        default => 'غير معروف'
    }
)
<span
    class="inline-flex whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium {{ in_array($value, ['active', 'available']) ? 'bg-emerald-50 text-emerald-900' : 'bg-stone-100 text-stone-700' }}">{{ $label }}</span>
