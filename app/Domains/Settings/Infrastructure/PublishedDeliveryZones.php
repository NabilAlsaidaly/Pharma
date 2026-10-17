<?php

namespace App\Domains\Settings\Infrastructure;

use App\Domains\Settings\Application\SettingGroups;
use App\Support\RevisionedStore;
use Illuminate\Support\Facades\DB;

/** Settings remains authoritative; this projection is informational, never an Order quotation. */
final class PublishedDeliveryZones
{
    public function query()
    {
        return DB::table('store_delivery_zones as z')->join('store_delivery_zones_revisions as r', function ($join): void {
            $join->on('r.record_id', '=', 'z.id')->on('r.version', '=', 'z.published_version');
        })->whereNull('z.archived_at')->whereNotNull('z.published_version');
    }

    public function listing(array $filters)
    {
        RevisionedStore::requireSchema(SettingGroups::get('zones'));

        return DB::transaction(function () use ($filters) {
            $rows = $this->query()->orderBy('r.sort_order')->orderBy('z.id')
                ->paginate($filters['per_page'] ?? 12, ['z.id', 'r.version as revision', 'r.name', 'r.governorate', 'r.city', 'r.fee_usd', 'r.fee_syp'], 'page', $filters['page'] ?? 1);
            $rows->setCollection($rows->getCollection()->map(fn ($r) => ['id' => $r->id, 'revision' => (string) $r->revision, 'name' => $r->name, 'governorate' => $r->governorate, 'city' => $r->city, 'fee_usd' => $r->fee_usd, 'fee_syp' => $r->fee_syp, 'fees_are_informational' => true, 'manual_confirmation_required' => true]));

            return $rows;
        });
    }
}
