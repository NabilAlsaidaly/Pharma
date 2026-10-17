<?php

namespace App\Domains\Delivery\Interfaces\Http;

use App\Domains\Delivery\Application\ManageDeliveryWorker;
use App\Domains\Delivery\Infrastructure\DeliveryWorkers;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class OwnerDeliveryController
{
    private function filters(Request $request, bool $history = false): array
    {
        $allowed = $history ? ['page', 'worker'] : ['page', 'q', 'active'];
        abort_if(array_diff(array_keys($request->query()), $allowed) !== [], 422);
        $filters = validator($request->query(), ['page' => 'sometimes|integer|between:1,1000', 'worker' => 'sometimes|uuid', 'q' => 'sometimes|nullable|string|max:100', 'active' => 'sometimes|nullable|in:0,1'])->validate();
        if (isset($filters['q'])) {
            abort_if(! mb_check_encoding($filters['q'], 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}<>]/u', $filters['q']), 422);
            $filters['q'] = trim($filters['q']);
        }

        return $filters;
    }

    public function index(Request $request, DeliveryWorkers $reader)
    {
        $filters = $this->filters($request);

        return view('owner.delivery.index', ['workers' => $reader->listing($filters), 'filters' => $filters]);
    }

    public function edit(Request $request, DeliveryWorkers $reader, ?string $id = null)
    {
        abort_if($request->query() !== [], 422);
        DeliveryWorkers::ready();

        return view('owner.delivery.edit', ['worker' => $id === null ? null : $reader->worker($id)]);
    }

    public function mutate(Request $request, ManageDeliveryWorker $service, ?string $id = null)
    {
        abort_if(array_diff(array_keys($request->all()), ['_token', 'operation_id', 'expected_version', 'action', 'values', 'confirm']) !== [], 422);
        $input = $request->except('_token');
        // Native HTML controls serialize booleans as strings. Typed command input stays strict.
        if (isset($input['values']) && is_array($input['values']) && array_key_exists('is_active', $input['values'])) {
            $active = $input['values']['is_active'];
            abort_unless(in_array($active, [true, false, '0', '1'], true), 422);
            $input['values']['is_active'] = $active === true || $active === '1';
        }
        $owner = Auth::guard('owner')->user();
        abort_unless($owner instanceof ManagementUser, 403);
        $target = $service->execute($id, $owner, $input);

        return redirect()->route('owner.delivery.edit', ['id' => $target])->with('status', 'حُفظ سجل التواصل وإجراؤه؛ لا إسناد أو إرسال رسائل.');
    }

    public function history(Request $request, DeliveryWorkers $reader)
    {
        return view('owner.delivery.history', ['events' => $reader->history($this->filters($request, true))]);
    }
}
