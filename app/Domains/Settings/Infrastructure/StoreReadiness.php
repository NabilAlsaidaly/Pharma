<?php

namespace App\Domains\Settings\Infrastructure;

use App\Domains\Content\Application\ContentTypes;
use App\Domains\Settings\Application\SettingGroups;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StoreReadiness
{
    public function read(): array
    {
        $tables = Schema::getTableListing(schemaQualified: false);
        $query = null;
        foreach (array_merge(SettingGroups::all(), ContentTypes::all()) as $key => $spec) {
            abort_unless(in_array($spec['table'], $tables, true) && in_array($spec['table'].'_revisions', $tables, true), 503);
            $part = DB::table($spec['table'])->selectRaw('? AS section, COUNT(*) AS total, COALESCE(SUM(archived_at IS NOT NULL),0) AS archived, COALESCE(SUM(published_version IS NOT NULL),0) AS published, COALESCE(SUM(archived_at IS NULL AND published_version IS NULL),0) AS unpublished', [$key]);
            $query = $query === null ? $part : $query->unionAll($part);
        }
        abort_unless(in_array('store_change_events', $tables, true), 503);

        return $query->get()->keyBy('section')->all();
    }
}
