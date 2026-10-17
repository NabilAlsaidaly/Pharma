<?php

namespace App\Domains\Content\Infrastructure\Education;

use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class EducationalStore
{
    public const TABLES = ['educational_articles', 'educational_article_revisions', 'educational_categories', 'educational_category_revisions', 'educational_revision_products', 'educational_revision_concerns', 'educational_revision_sources', 'educational_media', 'educational_revision_media', 'educational_content_events', 'educational_owner_locks'];

    public static function ready(): void
    {
        abort_if(array_diff(self::TABLES, Schema::getTableListing(schema: DB::connection()->getDatabaseName(), schemaQualified: false)) !== [], 503, 'المحتوى التعليمي غير متاح قبل تجهيز قاعدة البيانات.');
    }

    public static function actor(ManagementUser $owner): void
    {
        // Catalog audit FKs take an implicit S lock on Owner after locking Catalog rows.
        // Keep Owner revalidation S-compatible, and serialize editorial commands on our own row.
        $actual = ManagementUser::query()->whereKey($owner->id)->sharedLock()->firstOrFail();
        abort_unless($actual->role === 'owner' && $actual->status === 'active' && $actual->credential_version === $owner->credential_version, 403);
        // ON DUPLICATE KEY UPDATE obtains X directly (INSERT IGNORE can retain S then deadlock on upgrade).
        DB::table('educational_owner_locks')->upsert([['owner_id' => $owner->id]], ['owner_id'], ['owner_id']);
        DB::table('educational_owner_locks')->where('owner_id', $owner->id)->lockForUpdate()->first();
    }

    public static function replay(string $owner, string $op, string $hash): ?string
    {
        $event = DB::table('educational_content_events')->where('owner_id', $owner)->where('operation_id', $op)->lockForUpdate()->first();
        abort_if($event && ! hash_equals($event->payload_fingerprint, $hash), 409, 'معرّف العملية مستخدم لبيانات مختلفة.');

        return $event?->target_id;
    }

    public static function audit(string $owner, string $op, string $hash, string $type, string $id, string $action, string $before, string $after, array $fields): void
    {
        DB::table('educational_content_events')->insert(['id' => (string) Str::uuid7(), 'owner_id' => $owner, 'operation_id' => $op, 'payload_fingerprint' => $hash, 'target_type' => $type, 'target_id' => $id, 'action' => $action, 'previous_version' => $before, 'new_version' => $after, 'changed_fields' => ArticleDocument::encode($fields), 'request_id' => substr((string) request()->attributes->get('request_id'), 0, 100), 'created_at' => now()]);
    }

    public static function revision(string $id, string $version): array
    {
        $r = DB::table('educational_article_revisions')->where('article_id', $id)->where('version', $version)->first();
        abort_if(! $r, 404);
        $out = (array) $r;
        $out['version'] = (string) $r->version;
        $out['body'] = json_decode($r->body_document, true, 64, JSON_THROW_ON_ERROR);
        $out['is_featured'] = (bool) $r->is_featured;
        foreach (['products' => 'product_id', 'concerns' => 'concern_id'] as $kind => $column) {
            $out[$kind] = DB::table('educational_revision_'.$kind)->where('revision_id', $r->id)->orderBy('sort_order')->orderBy($column)->get([$column.' as id', 'sort_order'])->map(fn ($row) => (array) $row)->all();
        }
        $out['sources'] = DB::table('educational_revision_sources')->where('revision_id', $r->id)->orderBy('sort_order')->orderBy('url_key')->get(['title', 'url', 'url_key', 'note', 'sort_order'])->map(fn ($row) => (array) $row)->all();
        $out['images'] = DB::table('educational_revision_media')->where('revision_id', $r->id)->orderBy('slot')->get(['media_id', 'role', 'alt_text', 'slot'])->map(fn ($row) => (array) $row)->all();

        return $out;
    }
}
