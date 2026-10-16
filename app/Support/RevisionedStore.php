<?php

namespace App\Support;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Brick\Math\BigInteger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** SQL/revision coordination only. Domain adapters supply a fixed typed catalog, never browser table/column names. */
final class RevisionedStore
{
    public static function requireSchema(array $spec): void
    {
        abort_unless(Schema::hasTable($spec['table']) && Schema::hasTable($spec['table'].'_revisions') && Schema::hasTable('store_change_events'), 503, 'إعدادات المتجر غير متاحة قبل تجهيز قاعدة البيانات.');
    }

    public function mutate(array $spec, ?string $id, ManagementUser $owner, array $command, ?UploadedFile $file = null): string
    {
        self::requireSchema($spec);
        abort_if(array_diff(array_keys($command), ['operation_id', 'expected_version', 'action', 'values', 'confirm']) !== [], 422);
        $data = validator($command, ['operation_id' => ['required', 'uuid'], 'expected_version' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,19})$/D'], 'action' => ['required', 'in:save,publish,unpublish,archive,restore,media'], 'values' => ['sometimes', 'array'], 'confirm' => in_array($command['action'] ?? null, ['publish', 'unpublish', 'archive', 'restore'], true) ? ['required', 'accepted'] : ['prohibited']])->validate();
        abort_unless(BigInteger::of($data['expected_version'])->isLessThan('18446744073709551615'), 422);
        abort_if($id !== null && ! Str::isUuid($id), 422);
        $action = $data['action'];
        abort_if($action !== 'save' && ($data['values'] ?? []) !== [], 422);
        abort_if($action === 'media' && (! ($spec['media'] ?? false) || $file === null), 422);
        abort_if($action !== 'media' && $file !== null, 422);
        abort_if(in_array($action, ['publish', 'unpublish'], true) && ! $spec['public'], 422, 'هذا الإعداد تحضيري ولا يملك تفعيلًا تجارياً.');
        $values = BusinessInput::fields($data['values'] ?? [], $spec);
        ksort($values);
        $prepared = $file !== null ? app(BusinessRaster::class)->prepare($file) : null;
        $operation = strtolower($data['operation_id']);
        $fingerprint = hash('sha256', json_encode([$spec['table'], $id, $action, $data['expected_version'], $values, $prepared['digest'] ?? null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $fresh = null;
        try {
            return DB::transaction(function () use ($spec, $id, $owner, $data, $action, $values, $prepared, $operation, $fingerprint, &$fresh): string {
                // Single Owner row serializes initial singleton creation and competing operation identities.
                // Customer/Cart/Checkout never acquire this row; no reverse lock dependency is introduced.
                $actor = ManagementUser::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->role === 'owner' && $actor->status === 'active' && $actor->credential_version === $owner->credential_version, 403);
                $prior = DB::table('store_change_events')->where('owner_id', $owner->id)->where('operation_id', $operation)->first();
                if ($prior !== null) {
                    abort_unless(hash_equals($prior->payload_fingerprint, $fingerprint), 409, 'معرّف العملية مستخدم لبيانات مختلفة.');

                    return $prior->target_id;
                }
                $query = DB::table($spec['table']);
                $record = ($spec['singleton'] ? $query->where('singleton_key', 1) : $query->where('id', $id))->lockForUpdate()->first();
                abort_if($spec['singleton'] && $id !== null && $record?->id !== $id, 404);
                abort_if(! $spec['singleton'] && $id !== null && $record === null, 404);
                abort_unless((string) ($record->lock_version ?? '0') === $data['expected_version'], 409, 'تم تغيير هذا الإعداد في جلسة أخرى. حدّثي الصفحة.');
                abort_if($record === null && $action !== 'save', 409, 'احفظي البيانات أولاً.');
                abort_if($action === 'restore' ? ($record->archived_at === null) : ($record !== null && $record->archived_at !== null), 409, 'حالة الأرشفة تغيرت.');
                $columns = array_keys($spec['fields']);
                if ($spec['media'] ?? false) {
                    $columns[] = 'image_path';
                }
                $next = array_fill_keys($columns, null);
                foreach ($columns as $column) {
                    $next[$column] = $record->$column ?? null;
                }
                $next = array_replace($next, $values);
                // Slugs stay reserved for the lifetime of the document, including private drafts and archive.
                // Otherwise a draft rename could let another aggregate publish the still-live old URL.
                abort_if($record !== null && array_key_exists('slug', $values) && $values['slug'] !== $record->slug, 409, 'رابط الوثيقة ثابت لحفظ عناوينها المنشورة وسجلها.');
                if (isset($next['sort_order'])) {
                    $next['sort_order'] = (int) $next['sort_order'];
                }
                BusinessInput::complete($next, $spec);
                if ($action === 'media') {
                    // Reuse the same fresh generated path on a transaction retry, outside image decoding.
                    $fresh ??= app(BusinessRaster::class)->store($prepared);
                    $next['image_path'] = $fresh;
                }
                if ($action === 'publish' && ($spec['media'] ?? false)) {
                    abort_unless($next['image_path'] !== null || $spec['singleton'], 422, 'أضيفي صورة ووصفاً قبل نشر البنر.');
                    abort_if($next['image_path'] !== null && ! Storage::disk('local')->exists($next['image_path']), 422, 'الصورة غير متاحة.');
                }
                $target = $record->id ?? (string) Str::uuid7();
                $version = (string) BigInteger::of($data['expected_version'])->plus(1);
                $published = $action === 'publish' ? $version : ($record->published_version ?? null);
                if (in_array($action, ['unpublish', 'archive', 'restore'], true)) {
                    $published = null;
                }
                $row = $next + ['lock_version' => $version, 'published_version' => null, 'archived_at' => $action === 'archive' ? now() : null, 'updated_at' => now()];
                if (array_key_exists('governorate', $next)) {
                    $row['zone_key'] = hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($next['governorate'])).'|'.preg_replace('/\s+/u', ' ', trim($next['city'] ?? ''))));
                }
                if ($record === null) {
                    DB::table($spec['table'])->insert($row + ['id' => $target, 'singleton_key' => $spec['singleton'] ? 1 : null, 'created_at' => now()]);
                } else {
                    // Clear pointer temporarily only within this transaction before creating a new immutable revision.
                    DB::table($spec['table'])->where('id', $target)->update($row);
                }
                DB::table($spec['table'].'_revisions')->insert($next + ['id' => (string) Str::uuid7(), 'record_id' => $target, 'version' => $version, 'owner_id' => $owner->id, 'action' => $action, 'created_at' => now()]);
                DB::table($spec['table'])->where('id', $target)->update(['published_version' => $published]);
                $changed = array_values(array_filter($columns, fn ($key) => ($record->$key ?? null) !== $next[$key]));
                DB::table('store_change_events')->insert(['id' => (string) Str::uuid7(), 'owner_id' => $owner->id, 'target_type' => $spec['table'], 'target_id' => $target, 'action' => $action, 'previous_version' => $data['expected_version'], 'new_version' => $version, 'changed_fields' => json_encode($changed, JSON_THROW_ON_ERROR), 'operation_id' => $operation, 'payload_fingerprint' => $fingerprint, 'request_id' => substr((string) request()->attributes->get('request_id'), 0, 100), 'created_at' => now()]);

                return $target;
            }, 3);
        } catch (Throwable $exception) {
            if ($fresh !== null && ! Storage::disk('local')->delete($fresh)) {
                logger()->warning('business_media_compensation_failed', ['request_id' => request()->attributes->get('request_id')]);
            }
            throw $exception;
        }
    }
}
