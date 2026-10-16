<?php

namespace App\Domains\Orders\Interfaces\Http\Owner;

use App\Domains\ManagementDashboard\Infrastructure\ReadModels\DashboardSchema;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Application\Review\BulkReviewRequests;
use App\Domains\Orders\Application\Review\ReviewFilters;
use App\Domains\Orders\Application\Review\ReviewRequestAction;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use App\Domains\Orders\Infrastructure\Persistence\Queries\OwnerRequestReviewReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OwnerRequestReviewController
{
    private function owner(): ManagementUser
    {
        $owner = Auth::guard('owner')->user();
        abort_unless($owner instanceof ManagementUser, 403);

        return $owner;
    }

    public function index(Request $request, OwnerRequestReviewReader $reader)
    {
        $filters = ReviewFilters::validate($request->query());

        return view('owner.requests.index', ['records' => $reader->listing($filters), 'filters' => $filters]);
    }

    public function show(Request $request, OwnerRequestReviewReader $reader, string $id)
    {
        $data = validator($request->query(), ['page' => ['sometimes', 'integer', 'min:1', 'max:1000'], 'events_page' => ['sometimes', 'integer', 'min:1', 'max:1000']])->validate();
        abort_if(array_diff(array_keys($request->query()), ['page', 'events_page']) !== [], 422);

        $detail = $reader->detail($id, (int) ($data['page'] ?? 1), (int) ($data['events_page'] ?? 1));
        $reviewStatus = ReviewStatus::from($detail['review']->review_status ?? 'new');
        $transitions = array_filter(ReviewStatus::cases(), fn ($next) => $reviewStatus->allows($next));
        $operations = array_fill_keys(['transition', 'note', 'follow_up'], null);
        foreach ($operations as $key => $value) {
            $operations[$key] = (string) Str::uuid7();
        }

        return view('owner.requests.show', $detail + compact('reviewStatus', 'transitions', 'operations'));
    }

    public function mutate(ReviewMutationRequest $request, ReviewRequestAction $action, DashboardSchema $schema, string $id)
    {
        $schema->require('checkout_intents', 'preliminary_request_reviews', 'preliminary_request_review_events');
        $data = $request->validated();
        $event = $action->execute($id, $this->owner(), $data['operation_id'], $data['expected_version'], $data['kind'], $data['target'] ?? null, $data['note'] ?? null, requestId: (string) $request->attributes->get('request_id'));

        return redirect()->route('owner.requests.show', ['id' => $id])->with('status', 'حُفظ الإجراء الداخلي. الإصدار المسجل للعملية '.$event->resulting_version.'؛ تعرض الصفحة الحالة الحالية.');
    }

    public function preview(Request $request, OwnerRequestReviewReader $reader)
    {
        $data = $request->validate(['scope' => ['required', Rule::in(['ids', 'matching'])], 'ids' => ['required_if:scope,ids', 'array', 'min:1', 'max:100'], 'ids.*' => ['uuid', 'distinct'],
            'target' => ['required', Rule::in(['under_review', 'awaiting_contact'])], 'filters' => ['sometimes', 'array']]);
        abort_if(array_diff(array_keys($request->all()), ['scope', 'ids', 'target', 'filters', '_token']) !== [], 422);
        $filters = ReviewFilters::validate($data['filters'] ?? []);
        $query = $reader->query($filters);
        if ($data['scope'] === 'ids') {
            $query->whereIn('ci.id', $data['ids']);
        }
        $targets = $query->orderBy('ci.id')->limit(BulkReviewRequests::CAP + 1)->get();
        abort_unless($targets->count() > 0 && $targets->count() <= BulkReviewRequests::CAP, 422, 'حد العملية مئة طلب. ضيّقي المرشحات.');
        abort_if($data['scope'] === 'ids' && $targets->count() !== count($data['ids']), 409, 'تغيّرت المجموعة المحددة.');
        foreach ($targets as $target) {
            abort_unless(ReviewStatus::from($target->review_status)->allows(ReviewStatus::from($data['target']), true), 409, 'المجموعة تتضمن حالة غير مؤهلة. ضيّقي المرشحات.');
        }
        $preview = ['versions' => $targets->mapWithKeys(fn ($row) => [$row->id => (string) $row->review_version])->all(), 'operations' => $targets->mapWithKeys(fn ($row) => [$row->id => (string) Str::uuid7()])->all(),
            'filters' => $filters, 'target' => $data['target'], 'bulk_id' => (string) Str::uuid7(), 'scope' => $data['scope']];
        $token = (string) Str::uuid7();
        $request->session()->put('request_review_confirmation', ['token' => $token, 'preview' => $preview, 'expires' => now()->timestamp + 600]);

        return view('owner.requests.confirm', compact('targets', 'preview', 'token'));
    }

    public function confirm(Request $request, BulkReviewRequests $action)
    {
        $data = $request->validate(['token' => ['required', 'uuid'], 'confirm' => ['accepted']]);
        abort_if(array_diff(array_keys($request->all()), ['token', 'confirm', '_token']) !== [], 422);
        $pending = $request->session()->get('request_review_confirmation');
        abort_unless(is_array($pending) && hash_equals($pending['token'], $data['token']) && $pending['expires'] >= now()->timestamp, 409, 'انتهت المعاينة. أعيدي اختيار المجموعة.');
        $count = $action->execute($pending['preview'], $this->owner(), (string) $request->attributes->get('request_id'));

        // Retain one bounded expiring operation for an identical lost-response retry; no new events on replay.
        return redirect()->route('owner.requests.index')->with('status', 'حُفظت العملية للمجموعة المحددة: '.$count.' طلب. إعادة إرسال التأكيد نفسه لا تكرر الأحداث.');
    }
}
