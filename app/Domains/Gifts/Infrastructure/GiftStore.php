<?php

namespace App\Domains\Gifts\Infrastructure;

use App\Domains\Gifts\Application\GiftInput;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class GiftStore
{
    public static function ready(): void
    {
        $tables = ['gift_occasions', 'gift_packages', 'gift_package_revisions', 'gift_revision_components', 'gift_revision_images', 'gift_revision_occasions', 'gift_requests', 'gift_request_components', 'gift_request_reviews', 'gift_review_events', 'gift_change_events'];
        abort_if(array_diff($tables, Schema::getTableListing(schemaQualified: false)) !== [], 503, 'الهدايا غير متاحة قبل تجهيز قاعدة البيانات.');
    }

    public static function actor(ManagementUser $owner): void
    {
        $current = ManagementUser::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
        abort_unless($current->role === 'owner' && $current->status === 'active' && $current->credential_version === $owner->credential_version, 403);
    }

    public static function replay(string $owner, string $operation, string $fingerprint): ?string
    {
        $prior = DB::table('gift_change_events')->where('owner_id', $owner)->where('operation_id', $operation)->first();
        abort_if($prior !== null && ! hash_equals($prior->payload_fingerprint, $fingerprint), 409, 'معرّف العملية مستخدم لبيانات مختلفة.');

        return $prior?->target_id;
    }

    public static function audit(string $owner, string $operation, string $fingerprint, string $type, string $id, string $action, string $before, string $after, array $fields): void
    {
        DB::table('gift_change_events')->insert(['id' => (string) Str::uuid7(), 'owner_id' => $owner, 'operation_id' => $operation, 'payload_fingerprint' => $fingerprint, 'target_type' => $type, 'target_id' => $id, 'action' => $action, 'previous_version' => $before, 'new_version' => $after, 'changed_fields' => json_encode($fields, JSON_THROW_ON_ERROR), 'request_id' => substr((string) request()->attributes->get('request_id'), 0, 100), 'created_at' => now()]);
    }

    public static function revision(string $id, string $version): array
    {
        $r = DB::table('gift_package_revisions')->where('package_id', $id)->where('version', $version)->first();
        abort_if($r === null, 404);
        $value = (array) $r;
        $value['version'] = (string) $r->version;
        foreach (['components', 'images', 'occasions'] as $kind) {
            $q = DB::table('gift_revision_'.$kind)->where('revision_id', $r->id);
            if ($kind !== 'occasions') {
                $q->orderBy('sort_order');
            }
            $value[$kind] = $q->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $value;
    }

    public static function values(array $revision): array
    {
        return array_intersect_key($revision, GiftInput::PACKAGE_FIELDS);
    }
}
