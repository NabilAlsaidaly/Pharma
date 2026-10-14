@if (session('status'))
    <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">
        {{ session('status') }}</div>
@endif
@if ($errors->any())
    <div role="alert" aria-label="أخطاء النموذج"
        class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">
        <p class="mb-2 font-semibold">يرجى مراجعة البيانات</p>
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
