<?php

namespace App\Domains\Settings\Interfaces\Http;

use App\Domains\Content\Application\ContentTypes;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Settings\Application\ManageStoreSettings;
use App\Domains\Settings\Application\SettingGroups;
use App\Domains\Settings\Infrastructure\StoreReadiness;
use App\Support\BusinessRaster;
use App\Support\OwnerBusinessReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OwnerStoreSettingsController
{
    public function media(string $id)
    {
        return BusinessRaster::response($id);
    }

    public function index(Request $request)
    {
        abort_if($request->query() !== [], 422);

        return view('owner.store.overview', ['settings' => SettingGroups::all(), 'content' => ContentTypes::all(), 'readiness' => app(StoreReadiness::class)->read()]);
    }

    public function group(Request $request, OwnerBusinessReader $reader, string $group)
    {
        $spec = SettingGroups::get($group);
        $filters = $reader->pagination($request);
        if (! $spec['singleton']) {
            return view('owner.store.list', ['spec' => $spec, 'records' => $reader->listing($spec, (int) ($filters['page'] ?? 1)), 'section' => 'settings', 'type' => $group]);
        }

        return view('owner.store.edit', $reader->detail($spec, null, $filters) + ['spec' => $spec, 'section' => 'settings', 'type' => $group]);
    }

    public function edit(Request $request, OwnerBusinessReader $reader, string $group, ?string $id = null)
    {
        $spec = SettingGroups::get($group);
        abort_if($spec['singleton'], 404);

        return view('owner.store.edit', $reader->detail($spec, $id, $reader->pagination($request)) + ['spec' => $spec, 'section' => 'settings', 'type' => $group]);
    }

    public function mutate(Request $request, ManageStoreSettings $service, string $group, ?string $id = null)
    {
        $request->validate(['image' => ['sometimes', 'file']]);
        abort_if(array_diff(array_keys($request->all()), ['operation_id', 'expected_version', 'action', 'values', 'confirm', '_token', 'image']) !== [], 422);
        $owner = Auth::guard('owner')->user();
        abort_unless($owner instanceof ManagementUser, 403);
        $target = $service->execute($group, $id, $owner, $request->except(['_token', 'image']), $request->file('image'));

        return redirect()->route(SettingGroups::get($group)['singleton'] ? 'owner.settings.group' : 'owner.settings.edit', ['group' => $group] + (SettingGroups::get($group)['singleton'] ? [] : ['id' => $target]))->with('status', 'حُفظ الإجراء وإصداره في السجل. الدفع والتوصيل يبقيان تجهيزاً للمستقبل.');
    }

    public function history(Request $request, OwnerBusinessReader $reader)
    {
        $filters = $reader->pagination($request);
        abort_unless(Schema::hasTable('store_change_events'), 503);
        $events = DB::table('store_change_events as e')->join('management_users as u', 'u.id', '=', 'e.owner_id')
            ->select(['e.target_type', 'e.target_id', 'e.action', 'e.previous_version', 'e.new_version', 'e.changed_fields', 'e.created_at', 'u.display_name'])
            ->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25, ['*'], 'page', (int) ($filters['page'] ?? 1));

        return view('owner.store.history', compact('events'));
    }
}
