<nav aria-label="أقسام إعدادات المتجر" class="mb-6 flex flex-wrap gap-2 text-sm">
    <a class="rounded-lg border bg-white px-3 py-2" href="{{ route('owner.settings.index') }}">إعدادات المتجر</a>
    @foreach (\App\Domains\Settings\Application\SettingGroups::all() as $key => $tab)
        <a class="rounded-lg border bg-white px-3 py-2"
            href="{{ route('owner.settings.group', ['group' => $key]) }}">{{ $tab['label'] }}</a>
    @endforeach
    @foreach (\App\Domains\Content\Application\ContentTypes::all() as $key => $tab)
        <a class="rounded-lg border bg-white px-3 py-2"
            href="{{ route('owner.content.index', ['type' => $key]) }}">{{ $tab['label'] }}</a>
    @endforeach
    <a class="rounded-lg border bg-white px-3 py-2" href="{{ route('owner.settings.history') }}">سجل التغييرات والنشر</a>
</nav>
