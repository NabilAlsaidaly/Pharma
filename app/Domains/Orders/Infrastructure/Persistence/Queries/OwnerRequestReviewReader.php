<?php

namespace App\Domains\Orders\Infrastructure\Persistence\Queries;

use App\Domains\ManagementDashboard\Application\DashboardDateRange;
use App\Domains\ManagementDashboard\Application\SearchTerm;
use App\Domains\ManagementDashboard\Infrastructure\ReadModels\DashboardSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OwnerRequestReviewReader
{
    public function __construct(private DashboardSchema $schema) {}

    public function query(array $filters = []): Builder
    {
        $this->schema->require('checkout_intents');
        $this->schema->require('checkout_intents', 'checkout_intent_items', 'preliminary_request_reviews', 'preliminary_request_review_events');
        $query = DB::table('checkout_intents as ci')->leftJoin('preliminary_request_reviews as r', 'r.checkout_intent_id', '=', 'ci.id')
            ->select('ci.id', 'ci.status', 'ci.currency', 'ci.created_at')
            ->selectRaw("COALESCE(r.review_status, 'new') as review_status, COALESCE(r.lock_version, 0) as review_version, r.last_activity_at")
            ->selectSub(DB::table('checkout_intent_items')->selectRaw('COUNT(*)')->whereColumn('checkout_intent_id', 'ci.id'), 'items_count')
            ->selectSub(DB::table('checkout_intent_items')->selectRaw('COALESCE(MAX(requires_fulfillment_review),0)')->whereColumn('checkout_intent_id', 'ci.id'), 'needs_review');
        if (isset($filters['q'])) {
            $term = strtolower($filters['q']);
            if (! preg_match('/^[0-9a-f-]{2,36}$/D', $term)) {
                $query->whereRaw('0 = 1');
            } elseif (strlen($term) === 36) {
                $query->where('ci.id', $term);
            } else {
                $query->whereRaw("ci.id LIKE ? ESCAPE '!'", [SearchTerm::pattern($term, true)]);
            }
        }
        foreach (['status', 'currency'] as $field) {
            if (isset($filters[$field])) {
                $query->where('ci.'.$field, $filters[$field]);
            }
        }
        if (isset($filters['review_status'])) {
            $query->whereRaw("COALESCE(r.review_status,'new') = ?", [$filters['review_status']]);
        }
        if (isset($filters['reviewed'])) {
            $query->whereRaw("COALESCE(r.review_status,'new') ".($filters['reviewed'] === '1' ? '=' : '<>')." 'reviewed'");
        }
        if (isset($filters['fulfillment'])) {
            $query->whereExists(fn (Builder $items) => $items->selectRaw('1')->from('checkout_intent_items')->whereColumn('checkout_intent_id', 'ci.id')->where('requires_fulfillment_review', 1), 'and', $filters['fulfillment'] === '0');
        }
        foreach ([['from', 'to', 'ci.created_at'], ['activity_from', 'activity_to', 'r.last_activity_at']] as [$from, $to, $column]) {
            if (isset($filters[$from], $filters[$to])) {
                $range = DashboardDateRange::from(['period' => 'custom', 'from' => $filters[$from], 'to' => $filters[$to]]);
                $query->where($column, '>=', $range->start->utc()->format('Y-m-d H:i:s'))->where($column, '<', $range->end->utc()->format('Y-m-d H:i:s'));
            }
        }

        return $query;
    }

    public function listing(array $filters)
    {
        return $this->query($filters)->orderBy('ci.created_at', ($filters['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')->orderBy('ci.id')
            ->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1)->appends($filters);
    }

    public function detail(string $id, int $page = 1, int $eventsPage = 1): array
    {
        $this->schema->require('checkout_intents');
        $this->schema->require('checkout_intents', 'checkout_intent_items', 'preliminary_request_reviews', 'preliminary_request_review_events');

        return DB::transaction(function () use ($id, $page, $eventsPage): array {
            // One committed snapshot for header, review and history; no writes or read-triggered aggregate.
            $record = DB::table('checkout_intents')->select('id', 'status', 'currency', 'created_at', 'full_name', 'phone', 'email', 'governorate', 'city', 'address', 'notes')->where('id', $id)->first();
            abort_if($record === null, 404);
            $related = DB::table('checkout_intent_items')->select('id', 'product_id', 'variant_id', 'quantity', 'requires_fulfillment_review', 'line_order')
                ->where('checkout_intent_id', $id)->orderBy('line_order')->orderBy('id')->paginate(25, ['*'], 'page', $page)->appends(['events_page' => $eventsPage]);
            $review = DB::table('preliminary_request_reviews as r')->leftJoin('management_users as o', 'o.id', '=', 'r.last_reviewed_by_owner_id')
                ->where('r.checkout_intent_id', $id)->select('r.review_status', 'r.lock_version', 'r.review_started_at', 'r.review_completed_at', 'r.last_activity_at', 'o.display_name as reviewer')->first();
            $events = DB::table('preliminary_request_review_events as e')->join('management_users as o', 'o.id', '=', 'e.owner_id')->where('e.checkout_intent_id', $id)
                ->select('e.id', 'e.event_type', 'e.previous_status', 'e.new_status', 'e.note', 'e.created_at', 'e.resulting_version', 'o.display_name as author')
                ->orderByDesc('e.created_at')->orderByDesc('e.id')->paginate(25, ['*'], 'events_page', $eventsPage)->appends(['page' => $page]);

            return compact('record', 'related', 'review', 'events');
        });
    }

    public function statistics(DashboardDateRange $range): ?array
    {
        if (! $this->schema->has('checkout_intents') || ! $this->schema->has('checkout_intent_items', 'preliminary_request_reviews', 'preliminary_request_review_events')) {
            return null;
        }
        $counts = DB::table('checkout_intents as ci')->leftJoin('preliminary_request_reviews as r', 'r.checkout_intent_id', '=', 'ci.id')
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(COALESCE(r.review_status,'new')='new'),0) as new_count, COALESCE(SUM(r.review_status='under_review'),0) as under_review, COALESCE(SUM(r.review_status='awaiting_contact'),0) as awaiting_contact, COALESCE(SUM(r.review_status='reviewed'),0) as reviewed")
            ->selectRaw('COALESCE(SUM(EXISTS(SELECT 1 FROM checkout_intent_items i WHERE i.checkout_intent_id=ci.id AND i.requires_fulfillment_review=1)),0) as fulfillment')->first();
        $activity = DB::table('preliminary_request_review_events')->where('created_at', '>=', $range->start->utc()->format('Y-m-d H:i:s'))
            ->where('created_at', '<', $range->end->utc()->format('Y-m-d H:i:s'))->selectRaw('COUNT(*) as events, COUNT(DISTINCT checkout_intent_id) as requests')->first();

        return ['counts' => (array) $counts, 'activity' => (array) $activity];
    }
}
