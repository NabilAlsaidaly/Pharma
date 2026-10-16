<?php

namespace App\Domains\Gifts\Application;

use App\Domains\Gifts\Domain\GiftRules;
use App\Domains\Gifts\Infrastructure\GiftCatalog;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Support\BusinessRaster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ManageGiftPackage
{
    public function execute(?string $id, ManagementUser $owner, array $input, ?UploadedFile $file = null): string
    {
        GiftStore::ready();
        GiftInput::only($input, ['operation_id', 'expected_version', 'action', 'confirm', 'slug', 'values', 'components', 'images', 'occasion_ids', 'alt_text']);
        foreach (['values', 'components', 'images', 'occasion_ids'] as $key) {
            abort_if(array_key_exists($key, $input) && ! is_array($input[$key]), 422);
        }
        $operation = GiftInput::operation($input['operation_id'] ?? null);
        $version = GiftInput::version($input['expected_version'] ?? null);
        $action = $input['action'] ?? '';
        abort_unless(in_array($action, ['save', 'media', 'publish', 'unpublish', 'archive', 'restore'], true), 422);
        abort_if(in_array($action, ['publish', 'unpublish', 'archive', 'restore'], true) && ! in_array($input['confirm'] ?? null, ['1', 1, true], true), 422, 'أكدي الإجراء صراحة.');
        abort_if($action !== 'save' && array_intersect(array_keys($input), ['slug', 'values', 'components', 'images', 'occasion_ids']) !== [], 422);
        abort_if(($action === 'media') !== ($file !== null) || ($action !== 'media' && array_key_exists('alt_text', $input)), 422);
        $values = GiftInput::text($input['values'] ?? [], GiftInput::PACKAGE_FIELDS);
        abort_if(isset($values['availability']) && ! in_array($values['availability'], ['available', 'on_request', 'unavailable'], true), 422);
        $slug = $input['slug'] ?? null;
        abort_if($slug !== null && (! is_string($slug) || strlen($slug) > 80 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)), 422);
        $components = isset($input['components']) ? GiftInput::components($input['components']) : null;
        $occasions = $input['occasion_ids'] ?? null;
        if ($occasions !== null) {
            abort_unless(is_array($occasions) && array_is_list($occasions) && count($occasions) <= 20, 422);
            foreach ($occasions as &$occasion) {
                abort_unless(is_string($occasion) && Str::isUuid($occasion), 422);
                $occasion = strtolower($occasion);
            }
            unset($occasion);
            abort_unless(count(array_unique($occasions)) === count($occasions), 422);
            sort($occasions);
        }
        $images = isset($input['images']) ? $this->images($input['images']) : null;
        $alt = GiftInput::text(array_intersect_key($input, ['alt_text' => true]), ['alt_text' => ['وصف الصورة', 250]])['alt_text'] ?? null;
        abort_if($file !== null && $alt === null, 422, 'أضيفي وصف الصورة.');
        $prepared = $file ? app(BusinessRaster::class)->prepare($file) : null;
        ksort($values);
        $fingerprint = GiftInput::fingerprint([$id, $version, $action, $slug, $values, $components, $images, $occasions, $alt, $prepared['digest'] ?? null]);
        $fresh = null;
        try {
            return DB::transaction(function () use ($id, $owner, $operation, $version, $action, $slug, $values, $components, $images, $occasions, $alt, $prepared, $fingerprint, &$fresh): string {
                GiftStore::actor($owner);
                if ($prior = GiftStore::replay($owner->id, $operation, $fingerprint)) {
                    return $prior;
                }
                $package = $id ? DB::table('gift_packages')->where('id', $id)->lockForUpdate()->first() : null;
                abort_if($id && ! $package, 404);
                abort_unless((string) ($package->lock_version ?? '0') === $version, 409, 'تغيرت الباقة؛ حدّثي الصفحة.');
                abort_if(! $package && $action !== 'save', 409);
                abort_if($package && ($action === 'restore' ? $package->archived_at === null : $package->archived_at !== null), 409, 'حالة الأرشفة تغيرت.');
                abort_if($package && $slug !== null && $package->slug !== $slug, 409, 'رابط الباقة دائم ولا يتغير.');
                abort_if(! $package && ! $slug, 422, 'أدخلي رابطاً دائماً.');
                $old = $package ? GiftStore::revision($id, $version) : ['components' => [], 'images' => [], 'occasions' => []];
                $next = array_replace(array_fill_keys(array_keys(GiftInput::PACKAGE_FIELDS), null), ['availability' => 'unavailable', 'sort_order' => '0'], GiftStore::values($old), $values);
                abort_if(empty($next['name']) || ! preg_match('/[\p{L}\p{N}]/u', $next['name']) || $next['availability'] === null || $next['sort_order'] === null, 422, 'أكملي الاسم والتوفر والترتيب.');
                $next['sort_order'] = (int) $next['sort_order'];
                $cs = $components ?? $old['components'];
                $ims = $images ?? $old['images'];
                $existingMedia = array_column($old['images'], 'media_id');
                foreach ($ims as $image) {
                    abort_unless(in_array($image['media_id'], $existingMedia, true), 422, 'مرجع صورة غير معتمد.');
                }
                if ($action === 'media') {
                    abort_if(count($ims) >= 10, 422, 'الحد الأقصى عشر صور.');
                    $fresh ??= (string) Str::uuid7();
                    abort_unless(Storage::disk('local')->put('gifts/'.$fresh.'.png', $prepared['bytes']), 503, 'تعذر حفظ الصورة.');
                    $ims[] = ['media_id' => $fresh, 'alt_text' => $alt, 'sort_order' => count($ims), 'is_primary' => false];
                }
                $occasionIds = $occasions ?? array_column($old['occasions'], 'occasion_id');
                // Occasion → Catalog has no reverse Package dependency in occasion-only writes.
                $occasionRows = DB::table('gift_occasions')->whereIn('id', $occasionIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($occasionIds as $oid) {
                    $o = $occasionRows->get($oid);
                    $retained = in_array($oid, array_column($old['occasions'], 'occasion_id'), true);
                    abort_unless($o && (($o->is_active && $o->archived_at === null) || ($action !== 'publish' && $retained)), 409, 'المناسبة لم تعد متاحة.');
                }
                $inspection = app(GiftCatalog::class)->inspect($cs, $next['availability'], true);
                $oldKeys = array_column($old['components'], 'selection_key');
                foreach ($cs as &$c) {
                    if ($c['type'] === 'catalog') {
                        $key = $c['selection_key'];
                        abort_if(! ($inspection['valid'][$key] ?? false) && ($action === 'publish' || ! in_array($key, $oldKeys, true)), 409, 'أحد مكونات الباقة غير متاح؛ راجعي التركيب.');
                        $p = $inspection['products'][$c['product_id']] ?? null;
                        if ($action === 'publish' || ! in_array($key, $oldKeys, true)) {
                            $c['name'] = $p['name'];
                            $c['variant_name'] = $p['variants'][$c['variant_id']]['name'] ?? null;
                        } elseif (isset($components)) {
                            $priorComponent = collect($old['components'])->firstWhere('selection_key', $key);
                            $c['name'] = $priorComponent['name'];
                            $c['variant_name'] = $priorComponent['variant_name'];
                        }
                    }
                }
                unset($c);
                if ($action === 'publish') {
                    abort_unless($inspection['eligible'] && count($cs) > 0 && count($ims) > 0 && (GiftRules::positive($next['price_usd']) || GiftRules::positive($next['price_syp'])), 422, 'النشر يحتاج مكونات صالحة وصورة وسعراً موجباً.');
                    foreach ($ims as $im) {
                        abort_unless(Storage::disk('local')->exists('gifts/'.$im['media_id'].'.png'), 422, 'ملف الصورة غير متاح.');
                    }
                }
                $target = $package->id ?? (string) Str::uuid7();
                $newVersion = GiftRules::next($version);
                $published = $action === 'publish' ? $newVersion : ($package->published_version ?? null);
                if (in_array($action, ['unpublish', 'archive', 'restore'], true)) {
                    $published = null;
                }
                if (! $package) {
                    DB::table('gift_packages')->insert(['id' => $target, 'slug' => $slug, 'lock_version' => $newVersion, 'published_version' => null, 'created_at' => now(), 'updated_at' => now()]);
                }
                $rid = (string) Str::uuid7();
                DB::table('gift_package_revisions')->insert($next + ['id' => $rid, 'package_id' => $target, 'version' => $newVersion, 'owner_id' => $owner->id, 'action' => $action, 'created_at' => now()]);
                foreach ($cs as $c) {
                    DB::table('gift_revision_components')->insert(array_intersect_key($c, array_flip(['type', 'product_id', 'variant_id', 'name', 'variant_name', 'description', 'quantity', 'sort_order', 'selection_key'])) + ['id' => (string) Str::uuid7(), 'revision_id' => $rid]);
                }
                foreach ($ims as $im) {
                    DB::table('gift_revision_images')->insert(array_intersect_key($im, array_flip(['media_id', 'alt_text', 'sort_order', 'is_primary'])) + ['primary_slot' => $im['is_primary'] ? 1 : null, 'id' => (string) Str::uuid7(), 'revision_id' => $rid]);
                }
                foreach ($occasionIds as $oid) {
                    $oldOccasion = collect($old['occasions'])->firstWhere('occasion_id', $oid);
                    DB::table('gift_revision_occasions')->insert(['id' => (string) Str::uuid7(), 'revision_id' => $rid, 'occasion_id' => $oid, 'name' => $action === 'publish' || ! $oldOccasion ? $occasionRows[$oid]->name : $oldOccasion['name']]);
                }
                DB::table('gift_packages')->where('id', $target)->update(['lock_version' => $newVersion, 'published_version' => $published, 'archived_at' => $action === 'archive' ? now() : null, 'updated_at' => now()]);
                GiftStore::audit($owner->id, $operation, $fingerprint, 'package', $target, $action, $version, $newVersion, array_values(array_unique([...array_keys($values), ...($components !== null ? ['components'] : []), ...($images !== null || $prepared ? ['images'] : []), ...($occasions !== null ? ['occasions'] : [])])));

                return $target;
            }, 3);
        } catch (\Throwable $e) {
            if ($fresh !== null && ! Storage::disk('local')->delete('gifts/'.$fresh.'.png')) {
                logger()->warning('gift_media_compensation_failed', ['request_id' => request()->attributes->get('request_id')]);
            }
            throw $e;
        }
    }

    private function images(array $rows): array
    {
        abort_unless(array_is_list($rows) && count($rows) <= 10, 422);
        $out = [];
        $primary = 0;
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422);
            GiftInput::only($row, ['media_id', 'alt_text', 'sort_order', 'is_primary']);
            $v = validator($row, ['media_id' => 'required|uuid', 'sort_order' => 'required|integer|between:0,999999999', 'is_primary' => 'required|boolean'])->validate();
            abort_unless(is_int($row['sort_order']) && is_bool($row['is_primary']), 422);
            $alt = GiftInput::text(['alt_text' => $row['alt_text'] ?? null], ['alt_text' => ['وصف الصورة', 250]])['alt_text'];
            abort_if(! $alt || isset($out[$v['media_id']]), 422);
            $primary += (int) $row['is_primary'];
            $out[$v['media_id']] = $v + ['alt_text' => $alt];
        }
        abort_if($primary > 1, 422, 'اختاري صورة أساسية واحدة فقط.');

        return array_values($out);
    }
}
