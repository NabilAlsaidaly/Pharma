<?php

namespace App\Domains\Content\Application\Education;

use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Domains\Content\Infrastructure\Education\EducationalStore;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Support\BusinessInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManageEducationalCategory
{
    public function execute(?string $id, ManagementUser $owner, array $command): string
    {
        EducationalStore::ready();
        [$op, $before, $action] = ArticleInput::envelope($command, true);
        $id = $id === null ? null : ArticleInput::uuid($id);
        $values = $command['values'] ?? [];
        abort_unless(is_array($values), 422);
        ArticleDocument::only($values, ['name', 'description', 'is_active', 'sort_order']);
        $v = BusinessInput::fields(array_intersect_key($values, array_flip(['name', 'description'])), ['fields' => ['name' => ['اسم الفئة', 160], 'description' => ['وصف الفئة', 500]]]);
        if (isset($values['is_active'])) {
            abort_unless(is_bool($values['is_active']), 422);
            $v['is_active'] = $values['is_active'];
        }
        if (array_key_exists('is_active', $values)) {
            abort_unless(is_bool($values['is_active']), 422);
        }
        if (array_key_exists('sort_order', $values)) {
            $v['sort_order'] = ArticleInput::order($values['sort_order']);
        }
        $slug = $command['slug'] ?? null;
        if ($slug !== null) {
            abort_unless(is_string($slug) && strlen($slug) <= 80 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug), 422);
        }
        ksort($v);
        $hash = ArticleInput::fingerprint([$id, 'category', $action, $before, $slug, $v]);

        return DB::transaction(function () use ($owner, $id, $op, $before, $action, $slug, $v, $hash): string {
            EducationalStore::actor($owner);
            if ($prior = EducationalStore::replay($owner->id, $op, $hash)) {
                return $prior;
            }
            $old = $id ? DB::table('educational_categories')->where('id', $id)->lockForUpdate()->first() : null;
            abort_if($id && ! $old, 404);
            abort_unless((string) ($old->lock_version ?? '0') === $before, 409, 'الفئة تغيرت؛ حدّثي الصفحة.');
            abort_if(! $old && $action !== 'save', 409);
            abort_if($old && ($action === 'restore' ? ! $old->archived_at : $old->archived_at !== null), 409);
            abort_if($old && $slug !== null && $slug !== $old->slug, 409, 'الرابط دائم.');
            abort_if(! $old && ! $slug, 422);
            $next = array_replace(['name' => $old->name ?? null, 'description' => $old->description ?? null, 'is_active' => (bool) ($old->is_active ?? false), 'sort_order' => $old->sort_order ?? 0], $v);
            abort_if($next['name'] === null || $next['name'] === '', 422, 'اسم الفئة مطلوب.');
            if (in_array($action, ['archive', 'restore'], true)) {
                $next['is_active'] = false;
            }
            $target = $id ?? (string) Str::uuid7();
            $version = ArticleInput::next($before);
            $row = $next + ['lock_version' => $version, 'archived_at' => $action === 'archive' ? now() : null, 'updated_at' => now()];
            if ($old) {
                DB::table('educational_categories')->where('id', $target)->update($row);
            } else {
                DB::table('educational_categories')->insert($row + ['id' => $target, 'slug' => $slug, 'created_at' => now()]);
            }
            DB::table('educational_category_revisions')->insert($next + ['id' => (string) Str::uuid7(), 'category_id' => $target, 'version' => $version, 'owner_id' => $owner->id, 'action' => $action, 'created_at' => now()]);
            $fields = array_keys(array_filter($next, fn ($value, $key) => ! $old || $value != $old->$key, ARRAY_FILTER_USE_BOTH));
            EducationalStore::audit($owner->id, $op, $hash, 'category', $target, $action, $before, $version, $fields);

            return $target;
        }, 3);
    }
}
