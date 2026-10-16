@extends('owner.layout')
@section('title', 'إعدادات المتجر')
@section('content')
    <x-owner-page-heading title="إعدادات المتجر"
        description="احفظي معلومات المتجر ومحتواه وانشري ما اعتمدتِه للعملاء. إعداد الدفع والتوصيل للمستقبل لا يغيّر الطلبات الحالية." />
    @include('owner.store.tabs')
    <div class="grid gap-5 sm:grid-cols-2">
        @foreach ($settings as $key => $item)
            <a href="{{ route('owner.settings.group', ['group' => $key]) }}" class="min-w-0 rounded-2xl border bg-white p-6">
                <h2 class="font-bold">{{ $item['label'] }}</h2>
                <p class="mt-3 text-xs text-emerald-900">{{ $readiness[$key]->total ? ($item['public'] ? 'منشور: '.$readiness[$key]->published.' · غير منشور: '.$readiness[$key]->unpublished : 'محفوظ للتجهيز: '.$readiness[$key]->unpublished.' · بانتظار الربط') : 'غير مُعدّ بعد' }} · مؤرشف: {{ $readiness[$key]->archived }}</p>
                <p class="mt-3 text-sm leading-7 text-stone-600">
                    {{ $item['public'] ? 'معلومات للعرض فقط. احفظي المسودة ثم انشريها صراحةً لتصبح متاحة للعملاء.' : 'تجهيز محفوظ بإصدارات؛ بانتظار ربط مستقبلي. لا يُطبّق على الطلبات أو المدفوعات أو رسوم السلة.' }}
                </p>
            </a>
        @endforeach
        @foreach ($content as $key => $item)
            <a href="{{ route('owner.content.index', ['type' => $key]) }}" class="rounded-2xl border bg-white p-6">
                <h2 class="font-bold">{{ $item['label'] }}</h2>
                <p class="mt-3 text-xs text-emerald-900">منشور: {{ $readiness[$key]->published }} · مسودات غير منشورة: {{ $readiness[$key]->unpublished }} · مؤرشف: {{ $readiness[$key]->archived }}</p>
                <p class="mt-3 text-sm leading-7 text-stone-600">مسودات خاصة، معاينة، نشر صريح وإصدارات محفوظة. التعديل لا
                    يغيّر النسخة المنشورة حتى نشره.</p>
            </a>
        @endforeach
    </div>
    <section class="mt-6 rounded-2xl border bg-white p-6">
        <h2 class="font-bold">الميزات المتاحة وحالة التجهيز</h2>
        <p class="mt-3 text-sm leading-7">النشر وإلغاء النشر والأرشفة هي وسائل التحكم الفعلية في المحتوى. إيقاف استقبال
            الطلبات مؤجل وفق قرارك؛ يستمر استقبالها كالمعتاد. لا يوجد دفع أو شحن فعلي أو تحويل عملات. لا تُفترض معلومات متجر
            أو رسوم أو نصوص سياسات عند تركها فارغة.</p>
    </section>
@endsection
