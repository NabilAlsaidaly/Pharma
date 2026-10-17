<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educational_owner_locks', function (Blueprint $t): void {
            $t->uuid('owner_id')->primary();
            $t->foreign('owner_id')->references('id')->on('management_users')->restrictOnDelete();
        });
        Schema::create('educational_categories', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('slug', 80)->unique();
            $t->string('name', 160);
            $t->string('description', 500)->nullable();
            $t->boolean('is_active')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedBigInteger('lock_version');
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['archived_at', 'is_active', 'sort_order', 'id'], 'education_category_listing');
        });
        Schema::create('educational_category_revisions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('category_id')->constrained('educational_categories')->restrictOnDelete();
            $t->unsignedBigInteger('version');
            $t->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $t->enum('action', ['save', 'archive', 'restore']);
            $t->string('name', 160);
            $t->string('description', 500)->nullable();
            $t->boolean('is_active');
            $t->unsignedInteger('sort_order');
            $t->timestamp('created_at');
            $t->unique(['category_id', 'version'], 'education_category_revision');
        });
        Schema::create('educational_articles', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('slug', 80)->unique();
            $t->unsignedBigInteger('lock_version');
            $t->unsignedBigInteger('published_version')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['archived_at', 'published_version', 'id'], 'education_publication_listing');
        });
        Schema::create('educational_article_revisions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('article_id')->constrained('educational_articles')->restrictOnDelete();
            $t->unsignedBigInteger('version');
            $t->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $t->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $t->string('title', 160);
            $t->string('excerpt', 500)->nullable();
            $t->json('body_document');
            $t->foreignUuid('category_id')->nullable()->constrained('educational_categories')->restrictOnDelete();
            $t->string('category_name', 160)->nullable();
            $t->string('category_description', 500)->nullable();
            $t->string('seo_title', 160)->nullable();
            $t->string('meta_description', 300)->nullable();
            $t->enum('editorial_scope', ['general', 'health_related'])->nullable();
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('review_acknowledged')->default(false);
            $t->boolean('health_acknowledged')->default(false);
            $t->timestamp('created_at');
            $t->unique(['article_id', 'version'], 'education_revision_identity');
            $t->unique(['id', 'article_id'], 'education_revision_ownership');
            $t->index(['sort_order', 'created_at', 'article_id'], 'education_revision_order');
            $t->index(['category_id', 'id'], 'education_category_filter');
        });
        Schema::table('educational_articles', function (Blueprint $t): void {
            $t->foreign(['id', 'published_version'], 'education_publication_fk')->references(['article_id', 'version'])->on('educational_article_revisions')->restrictOnDelete();
        });
        foreach (['products' => 'product_id', 'concerns' => 'concern_id'] as $kind => $column) {
            Schema::create('educational_revision_'.$kind, function (Blueprint $t) use ($kind, $column): void {
                $t->uuid('id')->primary();
                $t->foreignUuid('revision_id')->constrained('educational_article_revisions')->restrictOnDelete();
                $t->foreignUuid($column)->constrained($kind)->restrictOnDelete();
                $t->unsignedInteger('sort_order');
                $t->unique(['revision_id', $column], 'education_'.$kind.'_unique');
                $t->index([$column, 'revision_id'], 'education_'.$kind.'_reverse');
                $t->index(['revision_id', 'sort_order', $column], 'education_'.$kind.'_order');
            });
        }
        Schema::create('educational_revision_sources', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('revision_id')->constrained('educational_article_revisions')->restrictOnDelete();
            $t->string('title', 200);
            $t->string('url', 2048);
            $t->char('url_key', 64);
            $t->string('note', 500)->nullable();
            $t->unsignedInteger('sort_order');
            $t->unique(['revision_id', 'url_key'], 'education_source_unique');
            $t->index(['revision_id', 'sort_order', 'id'], 'education_source_order');
        });
        Schema::create('educational_media', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('article_id')->constrained('educational_articles')->restrictOnDelete();
            $t->char('digest', 64);
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->timestamp('created_at');
            $t->unique(['id', 'article_id'], 'education_media_ownership');
        });
        Schema::create('educational_revision_media', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('revision_id');
            $t->uuid('article_id');
            $t->uuid('media_id');
            $t->enum('role', ['cover', 'inline']);
            $t->unsignedTinyInteger('slot');
            $t->string('alt_text', 250);
            $t->foreign(['revision_id', 'article_id'], 'education_media_revision_fk')->references(['id', 'article_id'])->on('educational_article_revisions')->restrictOnDelete();
            $t->foreign(['media_id', 'article_id'], 'education_media_article_fk')->references(['id', 'article_id'])->on('educational_media')->restrictOnDelete();
            $t->unique(['revision_id', 'media_id'], 'education_media_unique');
            $t->unique(['revision_id', 'slot'], 'education_media_slot_unique');
            $t->index(['media_id', 'revision_id'], 'education_media_visibility');
        });
        Schema::create('educational_content_events', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $t->uuid('operation_id');
            $t->char('payload_fingerprint', 64);
            $t->enum('target_type', ['article', 'category']);
            $t->uuid('target_id');
            $t->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $t->unsignedBigInteger('previous_version');
            $t->unsignedBigInteger('new_version');
            $t->json('changed_fields');
            $t->string('request_id', 100)->nullable();
            $t->timestamp('created_at');
            $t->unique(['owner_id', 'operation_id'], 'education_owner_operation');
            $t->index(['target_type', 'target_id', 'created_at', 'id'], 'education_target_history');
            $t->index(['created_at', 'id'], 'education_audit_history');
        });
        foreach (['educational_categories', 'educational_category_revisions', 'educational_article_revisions', 'educational_revision_products', 'educational_revision_concerns', 'educational_revision_sources'] as $table) {
            DB::statement('ALTER TABLE '.$table.' ADD CONSTRAINT '.$table.'_sort CHECK (sort_order <= 999999999)');
        }
        DB::statement('ALTER TABLE educational_revision_media ADD CONSTRAINT education_media_slot CHECK ((role = \'cover\' AND slot = 0) OR (role = \'inline\' AND slot BETWEEN 1 AND 5))');
        DB::statement('ALTER TABLE educational_media ADD CONSTRAINT education_image_dimensions CHECK (width BETWEEN 1 AND 4096 AND height BETWEEN 1 AND 4096 AND width * height <= 4194304)');
        DB::statement("ALTER TABLE educational_article_revisions ADD CONSTRAINT education_document_shape CHECK (JSON_TYPE(body_document) = 'OBJECT' AND JSON_CONTAINS_PATH(body_document, 'all', '$.schema_version', '$.blocks') AND JSON_TYPE(JSON_EXTRACT(body_document, '$.schema_version')) = 'INTEGER' AND JSON_EXTRACT(body_document, '$.schema_version') = 1 AND JSON_TYPE(JSON_EXTRACT(body_document, '$.blocks')) = 'ARRAY' AND JSON_LENGTH(body_document, '$.blocks') <= 100)");
        foreach (['educational_articles' => 'lock_version', 'educational_categories' => 'lock_version', 'educational_article_revisions' => 'version', 'educational_category_revisions' => 'version'] as $table => $column) {
            DB::statement('ALTER TABLE '.$table.' ADD CONSTRAINT '.$table.'_positive CHECK ('.$column.' > 0)');
        }
        DB::statement('ALTER TABLE educational_content_events ADD CONSTRAINT education_event_version CHECK (new_version > previous_version)');
    }

    public function down(): void
    {
        Schema::table('educational_articles', fn (Blueprint $t) => $t->dropForeign('education_publication_fk'));
        foreach (['educational_content_events', 'educational_revision_media', 'educational_media', 'educational_revision_sources', 'educational_revision_concerns', 'educational_revision_products', 'educational_article_revisions', 'educational_articles', 'educational_category_revisions', 'educational_categories', 'educational_owner_locks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
