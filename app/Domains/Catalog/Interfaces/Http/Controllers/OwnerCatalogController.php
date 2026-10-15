<?php

namespace App\Domains\Catalog\Interfaces\Http\Controllers;

use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\Administration\CatalogRecords;
use App\Domains\Catalog\Application\Administration\CatalogSelection;
use App\Domains\Catalog\Application\Administration\ExecuteCatalogBulkAction;
use App\Domains\Catalog\Application\Administration\ManageCatalogMedia;
use App\Domains\Catalog\Application\Administration\WriteCatalogRecord;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Interfaces\Http\Requests\OwnerCatalogRequest;
use App\Domains\ManagementDashboard\Application\SearchTerm;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerCatalogController
{
    private function change(Request $request, ?string $bulk = null): CatalogChange
    {
        $owner = Auth::guard('owner')->user();
        abort_unless($owner instanceof ManagementUser, 403);

        return new CatalogChange($owner, (string) $request->attributes->get('request_id'), $bulk);
    }

    public function form(Request $request, string $type, ?string $id = null)
    {
        abort_unless(isset(WriteCatalogRecord::FIELDS[$type]), 404);
        $record = $id ? CatalogRecords::model($type)->newQuery()->findOrFail($id) : CatalogRecords::model($type);
        if ($type === 'variants' && ! $id) {
            $request->validate(['parent' => ['required', 'uuid']]);
            Product::query()->findOrFail($request->input('parent'));
            $record->setAttribute('product_id', $request->input('parent'));
        }
        $request->validate(['brand_q' => ['nullable', 'string', 'max:100'], 'concern_q' => ['nullable', 'string', 'max:100']]);
        $brands = $type === 'products' ? Brand::query()->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [SearchTerm::pattern($request->string('brand_q')->toString())])->orWhereKey($record->getAttribute('brand_id')))->orderByRaw('id = ? DESC', [$record->getAttribute('brand_id') ?? ''])->orderBy('name')->orderBy('id')->limit(50)->get(['id', 'name']) : collect();
        $concerns = $record instanceof Product && $record->exists ? $record->concerns()->withTrashed()->orderBy('concerns.id')->limit(101)->pluck('concerns.id')->all() : [];
        $options = collect();
        if ($type === 'products') {
            $query = Concern::withTrashed()->where(fn ($q) => $q->whereIn('id', $concerns)->orWhere(fn ($current) => $current->whereNull('deleted_at')->whereRaw("name LIKE ? ESCAPE '!'", [SearchTerm::pattern($request->string('concern_q')->toString())])));
            if ($concerns !== []) {
                $query->orderByRaw('CASE WHEN id IN ('.implode(',', array_fill(0, count($concerns), '?')).') THEN 0 ELSE 1 END', $concerns);
            }
            $options = $query->orderBy('name')->orderBy('id')->limit(count($concerns) + 50)->get(['id', 'name', 'deleted_at']);
        }
        $variants = $record instanceof Product && $record->exists ? $record->variants()->paginate(25, ['*'], 'variants_page') : null;
        $images = $record instanceof Product && $record->exists ? $record->images()->paginate(20, ['*'], 'images_page') : null;

        return view('owner.catalog.form', compact('type', 'record', 'brands', 'concerns', 'options', 'variants', 'images'));
    }

    public function save(OwnerCatalogRequest $request, string $type, ?string $id = null)
    {
        $parent = $type === 'variants' ? ($id ? CatalogRecords::model($type)->newQuery()->findOrFail($id)->getAttribute('product_id') : $request->input('parent')) : null;
        $record = app(WriteCatalogRecord::class)->execute($type, $id, $request->validated(), $this->change($request), $parent);

        return redirect()->route('owner.catalog.edit', ['type' => $type, 'id' => $record->getKey()])->with('status', 'تم حفظ التغييرات.');
    }

    public function recycle(Request $request, string $type)
    {
        $filters = $this->filters($request, ['archived' => true]);
        $records = app(CatalogSelection::class)->query($type, $filters)->orderByDesc('deleted_at')->orderBy('id')->paginate(25)->withQueryString();

        return view('owner.catalog.recycle', compact('type', 'records', 'filters'));
    }

    private function filters(Request $request, array $extra = []): array
    {
        $raw = $request->input('filters', $request->query());
        abort_unless(is_array($raw) && array_diff(array_keys($raw), ['q', 'status', 'brand', 'concern', 'availability', 'parent', 'archived', 'page', 'per_page', 'sort']) === [], 422, 'مرشحات غير مسموحة.');
        $data = validator($raw, [
            'q' => ['nullable', 'string', 'min:2', 'max:100', 'not_regex:/[\p{Cc}\p{Cf}]/u'],
            'status' => ['nullable', Rule::in(['active', 'hidden'])], 'brand' => ['nullable', 'uuid'], 'concern' => ['nullable', 'uuid'],
            'availability' => ['nullable', Rule::in(['available', 'on_request', 'unavailable'])], 'parent' => ['nullable', 'uuid'],
            'archived' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['sometimes', Rule::in([10, 25, 50])], 'sort' => ['sometimes', Rule::in(['name', 'newest', 'oldest'])],
        ])->validate();

        return array_filter(array_replace($data, $extra), fn ($value) => $value !== null && $value !== '');
    }

    public function preview(Request $request, string $type)
    {
        $data = $request->validate(['scope' => ['required', Rule::in(['ids', 'matching'])], 'ids' => ['required_if:scope,ids', 'array', 'min:1', 'max:100'], 'ids.*' => ['uuid', 'distinct'], 'action' => ['required', 'string'], 'filters' => ['sometimes', 'array']]);
        $filters = $this->filters($request);
        $archived = (bool) ($filters['archived'] ?? false);
        abort_unless(isset(ExecuteCatalogBulkAction::actions($type, $archived)[$data['action']]), 422);
        $query = app(CatalogSelection::class)->query($type, $filters);
        if ($data['scope'] === 'ids') {
            $query->whereKey($data['ids']);
        }
        $count = (clone $query)->count();
        abort_unless($count > 0 && $count <= ExecuteCatalogBulkAction::CAP, 422, 'حد العملية مئة سجل. ضيّقي المرشحات.');
        $targets = $query->orderBy('id')->limit(ExecuteCatalogBulkAction::CAP + 1)->get($type === 'images' ? ['id', 'lock_version'] : ['id', 'lock_version', 'name']);
        abort_unless($targets->count() === $count, 409, 'تغيّرت المجموعة. أعيدي المعاينة.');
        $versions = $targets->pluck('lock_version', 'id')->all();
        abort_if($data['scope'] === 'ids' && $count !== count($data['ids']), 409, 'تغيّرت السجلات المحددة.');
        $preview = ['type' => $type, 'scope' => $data['scope'], 'filters' => $filters, 'versions' => $versions, 'archived' => $archived, 'action' => $data['action'], 'expires' => now()->timestamp + 600];
        $token = (string) Str::uuid7();
        // One bounded pending confirmation per session; no unbounded selection accumulation.
        $request->session()->put('catalog_confirmation', ['token' => $token, 'preview' => $preview]);
        $impact = $type === 'brands' ? Product::withTrashed()->whereIn('brand_id', array_keys($versions))->count() : null;

        return view('owner.catalog.confirm', compact('preview', 'token', 'count', 'impact', 'targets'));
    }

    public function execute(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'uuid'], 'confirm' => ['accepted']]);
        $pending = $request->session()->get('catalog_confirmation');
        abort_unless(is_array($pending) && hash_equals($pending['token'], $data['token']) && $pending['preview']['expires'] >= now()->timestamp, 409, 'انتهت المعاينة. أعيدي تحديد الإجراء.');
        app(ExecuteCatalogBulkAction::class)->execute($pending['preview'], $this->change($request, (string) Str::uuid7()));
        $request->session()->forget('catalog_confirmation');
        $type = $pending['preview']['type'];

        return redirect()->route($pending['preview']['archived'] || in_array($type, ['variants', 'images'], true) ? 'owner.catalog.recycle' : 'owner.'.$type.'.index', in_array($type, ['variants', 'images'], true) || $pending['preview']['archived'] ? ['type' => $type] : [])->with('status', 'تم تنفيذ العملية على جميع السجلات المحددة.');
    }

    public function upload(Request $request, string $type, string $id)
    {
        $data = $request->validate(['lock_version' => ['required', 'regex:/^[1-9][0-9]{0,15}$/D'], 'image' => ['required', 'file', 'max:5120']]);
        app(ManageCatalogMedia::class)->upload($type, $id, $data['lock_version'], $request->file('image'), $this->change($request));

        return redirect()->route('owner.catalog.edit', compact('type', 'id'))->with('status', 'تم حفظ الصورة.');
    }

    public function image(Request $request, string $id, string $image)
    {
        $data = $request->validate(['lock_version' => ['required', 'regex:/^[1-9][0-9]{0,15}$/D'], 'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'], 'is_primary' => ['required', 'boolean']]);
        app(ManageCatalogMedia::class)->update($id, $image, $data['lock_version'], (int) $data['sort_order'], (bool) $data['is_primary'], $this->change($request));

        return redirect()->route('owner.catalog.edit', ['type' => 'products', 'id' => $id])->with('status', 'تم تحديث الصورة.');
    }
}
