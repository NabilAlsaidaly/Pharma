<?php

namespace App\Domains\Gifts\Interfaces\Http;

use App\Domains\Gifts\Application\GiftInput;
use App\Domains\Gifts\Application\ManageGiftOccasion;
use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Application\ReviewGiftRequest;
use App\Domains\Gifts\Infrastructure\GiftRequests;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\Gifts\Infrastructure\OwnerGiftReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OwnerGiftController
{
    public function index(Request $request, OwnerGiftReader $reader)
    {
        $filters = OwnerGiftReader::filters($request->query());

        return view('owner.gifts.index', ['records' => $reader->packages($filters)]);
    }

    public function edit(Request $request, OwnerGiftReader $reader, ?string $id = null)
    {
        return view('owner.gifts.edit', $reader->package($id, OwnerGiftReader::filters($request->query())));
    }

    public function mutate(Request $request, ManageGiftPackage $command, ?string $id = null)
    {
        GiftInput::only($request->all(), ['_token', 'operation_id', 'expected_version', 'action', 'confirm', 'slug', 'values', 'components', 'images', 'occasion_ids', 'alt_text', 'image', '_components_present', '_images_present', '_occasions_present']);
        $request->validate(['image' => ['sometimes', 'file']]);
        $input = $request->except(['_token', 'image', '_components_present', '_images_present', '_occasions_present']);
        foreach (['components', 'images', 'occasions'] as $kind) {
            if ($request->has('_'.$kind.'_present')) {
                $input[$kind === 'occasions' ? 'occasion_ids' : $kind] ??= [];
            }
        }
        if (isset($input['components'])) {
            abort_unless(is_array($input['components']), 422);
            $input['components'] = array_values($input['components']);
            foreach ($input['components'] as &$c) {
                abort_unless(is_array($c), 422);
                GiftInput::only($c, ['type', 'selection', 'name', 'description', 'quantity', 'sort_order']);
                if (($c['type'] ?? '') === 'catalog') {
                    abort_unless(isset($c['selection']) && is_string($c['selection']), 422);
                    $parts = explode('|', $c['selection']);
                    abort_unless(count($parts) === 2, 422);
                    $c['product_id'] = $parts[0];
                    $c['variant_id'] = $parts[1] ?: null;
                    unset($c['selection']);
                }
                foreach (['quantity', 'sort_order'] as $n) {
                    abort_unless(isset($c[$n]) && is_string($c[$n]) && preg_match('/^(0|[1-9][0-9]{0,8})$/D', $c[$n]), 422);
                    $c[$n] = (int) $c[$n];
                }
            }
            unset($c);
        }
        if (isset($input['images'])) {
            abort_unless(is_array($input['images']), 422);
            $input['images'] = array_values($input['images']);
            foreach ($input['images'] as &$im) {
                abort_unless(is_array($im) && isset($im['sort_order']) && is_string($im['sort_order']) && preg_match('/^(0|[1-9][0-9]{0,8})$/D', $im['sort_order']), 422);
                $im['sort_order'] = (int) $im['sort_order'];
                abort_if(isset($im['is_primary']) && $im['is_primary'] !== '1', 422);
                $im['is_primary'] = isset($im['is_primary']);
            }
            unset($im);
        }
        $target = $command->execute($id, $request->user('owner'), $input, $request->file('image'));

        return redirect()->route('owner.gifts.edit', ['id' => $target])->with('status', 'حُفظ إجراء الباقة. تعديل المسودة لا يغير النسخة المنشورة السابقة.');
    }

    public function media(Request $request, string $id)
    {
        GiftStore::ready();
        abort_if($request->query() !== [], 422);
        abort_unless(DB::table('gift_revision_images')->where('media_id', $id)->exists(), 404);

        return GiftApiController::image($id);
    }

    public function occasions(Request $request)
    {
        GiftStore::ready();
        $filters = GiftRequests::filters($request->query());

        return view('owner.gifts.occasions', ['records' => DB::table('gift_occasions')->orderBy('sort_order')->orderBy('id')->paginate(25, ['*'], 'page', $filters['page'] ?? 1)]);
    }

    public function occasionEdit(Request $request, ?string $id = null)
    {
        GiftStore::ready();
        abort_if($request->query() !== [], 422);
        $record = $id ? DB::table('gift_occasions')->where('id', $id)->first() : null;
        abort_if($id && ! $record, 404);

        return view('owner.gifts.occasion', compact('record'));
    }

    public function occasionMutate(Request $request, ManageGiftOccasion $command, ?string $id = null)
    {
        $target = $command->execute($id, $request->user('owner'), $request->except('_token'));

        return redirect()->route('owner.gifts.occasion.edit', ['id' => $target])->with('status', 'حُفظت المناسبة. أرشفتها تمنع اختيارها في طلب جديد.');
    }

    public function requests(Request $request, GiftRequests $reader)
    {
        $filters = GiftRequests::filters($request->query(), true);

        return view('owner.gifts.requests', ['records' => $reader->listing($filters), 'filters' => $filters]);
    }

    public function requestDetail(Request $request, GiftRequests $reader, string $id)
    {
        $filters = GiftRequests::filters($request->query());
        $data = DB::transaction(function () use ($reader, $id, $filters): array {
            return ['detail' => $reader->detail($id), 'events' => DB::table('gift_review_events as e')->join('management_users as o', 'o.id', '=', 'e.owner_id')->where('e.request_id', $id)->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25, ['e.*', 'o.display_name as actor_name'], 'page', $filters['page'] ?? 1)];
        });

        return view('owner.gifts.request', $data);
    }

    public function review(Request $request, ReviewGiftRequest $command, string $id)
    {
        $command->execute($id, $request->user('owner'), $request->except('_token'));

        return redirect()->route('owner.gifts.requests.show', ['id' => $id])->with('status', 'حُفظت المراجعة الداخلية دون تأكيد شراء أو دفع.');
    }

    public function history(Request $request)
    {
        GiftStore::ready();
        $filters = GiftRequests::filters($request->query());

        return view('owner.gifts.history', ['records' => DB::table('gift_change_events as e')->join('management_users as o', 'o.id', '=', 'e.owner_id')->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25, ['e.target_type', 'e.target_id', 'e.action', 'e.previous_version', 'e.new_version', 'e.created_at', 'o.display_name as actor_name'], 'page', $filters['page'] ?? 1)]);
    }
}
