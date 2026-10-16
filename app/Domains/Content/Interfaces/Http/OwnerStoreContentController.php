<?php

namespace App\Domains\Content\Interfaces\Http;

use App\Domains\Content\Application\ContentTypes;
use App\Domains\Content\Application\ManageStoreContent;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Support\OwnerBusinessReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerStoreContentController
{
    public function index(Request $request, OwnerBusinessReader $reader, string $type)
    {
        $spec = ContentTypes::get($type);
        $filters = $reader->pagination($request);

        return view('owner.store.list', ['spec' => $spec, 'records' => $reader->listing($spec, (int) ($filters['page'] ?? 1)), 'section' => 'content', 'type' => $type]);
    }

    public function edit(Request $request, OwnerBusinessReader $reader, string $type, ?string $id = null)
    {
        $spec = ContentTypes::get($type);

        return view('owner.store.edit', $reader->detail($spec, $id, $reader->pagination($request)) + ['spec' => $spec, 'section' => 'content', 'type' => $type]);
    }

    public function mutate(Request $request, ManageStoreContent $service, string $type, ?string $id = null)
    {
        $request->validate(['image' => ['sometimes', 'file']]);
        abort_if(array_diff(array_keys($request->all()), ['operation_id', 'expected_version', 'action', 'values', 'confirm', '_token', 'image']) !== [], 422);
        $owner = Auth::guard('owner')->user();
        abort_unless($owner instanceof ManagementUser, 403);
        $target = $service->execute($type, $id, $owner, $request->except(['_token', 'image']), $request->file('image'));

        return redirect()->route('owner.content.edit', ['type' => $type, 'id' => $target])->with('status', 'حُفظ الإجراء وإصداره. المسودات لا تظهر للعملاء، والنشر يحتاج تأكيداً صريحاً.');
    }
}
