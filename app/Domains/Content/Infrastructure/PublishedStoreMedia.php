<?php

namespace App\Domains\Content\Infrastructure;

use App\Domains\Content\Application\ContentTypes;
use App\Domains\Settings\Application\SettingGroups;
use App\Support\RevisionedStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PublishedStoreMedia
{
    public function authorize(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);
        $query = null;
        foreach ([SettingGroups::get('profile'), ContentTypes::get('banners')] as $spec) {
            RevisionedStore::requireSchema($spec);
            $part = DB::table($spec['table'].' as d')->join($spec['table'].'_revisions as r', function ($join): void {
                $join->on('r.record_id', '=', 'd.id')->on('r.version', '=', 'd.published_version');
            })->whereNull('d.archived_at')->where('r.image_path', 'storefront/'.$id.'.png')->select('d.id');
            $query = $query === null ? $part : $query->unionAll($part);
        }
        // One statement snapshot defines admission; no filesystem reads or response streaming under SQL locks.
        abort_unless(DB::query()->fromSub($query, 'published_media')->exists(), 404);
    }
}
