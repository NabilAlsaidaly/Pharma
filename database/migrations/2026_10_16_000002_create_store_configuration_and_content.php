<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se1_list_index');
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->text('about')->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('hours', 300)->nullable();
            $table->string('facebook', 500)->nullable();
            $table->string('instagram', 500)->nullable();
            $table->string('whatsapp', 500)->nullable();
            $table->string('tiktok', 500)->nullable();
            $table->string('youtube', 500)->nullable();
            $table->string('image_path', 200)->nullable();
        });
        DB::statement('ALTER TABLE store_profiles ADD CONSTRAINT se1_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE store_profiles ADD CONSTRAINT se1_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE store_profiles ADD CONSTRAINT se1_a_check2 CHECK (singleton_key = 1 AND singleton_key IS NOT NULL)');
        Schema::create('store_profiles_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se1_revision_unique');
            $table->foreign('record_id', 'se1_record_fk')->references('id')->on('store_profiles')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->text('about')->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('hours', 300)->nullable();
            $table->string('facebook', 500)->nullable();
            $table->string('instagram', 500)->nullable();
            $table->string('whatsapp', 500)->nullable();
            $table->string('tiktok', 500)->nullable();
            $table->string('youtube', 500)->nullable();
            $table->string('image_path', 200)->nullable();
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE store_profiles_revisions ADD CONSTRAINT se1_r_check0 CHECK (version > 0)');
        Schema::table('store_profiles', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se1_published_fk')->references(['record_id', 'version'])->on('store_profiles_revisions')->restrictOnDelete();
        });
        Schema::table('store_profiles_revisions', fn (Blueprint $table) => $table->index('image_path', 'se1_media_index'));
        Schema::create('store_currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se2_list_index');
            $table->string('first_currency', 3);
            $table->string('usd_label', 40);
            $table->string('syp_label', 40);
            $table->string('usd_symbol', 12)->nullable();
            $table->string('syp_symbol', 12)->nullable();
        });
        DB::statement('ALTER TABLE store_currencies ADD CONSTRAINT se2_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE store_currencies ADD CONSTRAINT se2_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE store_currencies ADD CONSTRAINT se2_a_check2 CHECK (singleton_key = 1 AND singleton_key IS NOT NULL)');
        DB::statement("ALTER TABLE store_currencies ADD CONSTRAINT se2_a_check3 CHECK (first_currency IN ('USD', 'SYP'))");
        Schema::create('store_currencies_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se2_revision_unique');
            $table->foreign('record_id', 'se2_record_fk')->references('id')->on('store_currencies')->restrictOnDelete();
            $table->string('first_currency', 3);
            $table->string('usd_label', 40);
            $table->string('syp_label', 40);
            $table->string('usd_symbol', 12)->nullable();
            $table->string('syp_symbol', 12)->nullable();
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE store_currencies_revisions ADD CONSTRAINT se2_r_check0 CHECK (version > 0)');
        DB::statement("ALTER TABLE store_currencies_revisions ADD CONSTRAINT se2_r_check1 CHECK (first_currency IN ('USD', 'SYP'))");
        Schema::table('store_currencies', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se2_published_fk')->references(['record_id', 'version'])->on('store_currencies_revisions')->restrictOnDelete();
        });
        Schema::create('store_payment_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se3_list_index');
            $table->string('mode', 7);
            $table->decimal('percentage', 5, 2);
            $table->string('explanation', 1000)->nullable();
        });
        DB::statement('ALTER TABLE store_payment_policies ADD CONSTRAINT se3_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE store_payment_policies ADD CONSTRAINT se3_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE store_payment_policies ADD CONSTRAINT se3_a_check2 CHECK (singleton_key = 1 AND singleton_key IS NOT NULL)');
        DB::statement("ALTER TABLE store_payment_policies ADD CONSTRAINT se3_a_check3 CHECK ((mode = 'full' AND percentage = 100.00) OR (mode = 'deposit' AND percentage > 0 AND percentage < 100.00))");
        Schema::create('store_payment_policies_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se3_revision_unique');
            $table->foreign('record_id', 'se3_record_fk')->references('id')->on('store_payment_policies')->restrictOnDelete();
            $table->string('mode', 7);
            $table->decimal('percentage', 5, 2);
            $table->string('explanation', 1000)->nullable();
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE store_payment_policies_revisions ADD CONSTRAINT se3_r_check0 CHECK (version > 0)');
        DB::statement("ALTER TABLE store_payment_policies_revisions ADD CONSTRAINT se3_r_check1 CHECK ((mode = 'full' AND percentage = 100.00) OR (mode = 'deposit' AND percentage > 0 AND percentage < 100.00))");
        Schema::table('store_payment_policies', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se3_published_fk')->references(['record_id', 'version'])->on('store_payment_policies_revisions')->restrictOnDelete();
        });
        Schema::create('store_delivery_zones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se4_list_index');
            $table->char('zone_key', 64)->unique();
            $table->string('name', 160);
            $table->string('governorate', 100);
            $table->string('city', 100)->nullable();
            $table->decimal('fee_usd', 18, 2)->nullable();
            $table->decimal('fee_syp', 18, 2)->nullable();
            $table->unsignedInteger('sort_order');
            $table->string('conditions', 1000)->nullable();
            $table->index(['archived_at', 'sort_order', 'id'], 'se4_order_index');
        });
        DB::statement('ALTER TABLE store_delivery_zones ADD CONSTRAINT se4_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE store_delivery_zones ADD CONSTRAINT se4_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE store_delivery_zones ADD CONSTRAINT se4_a_check2 CHECK (singleton_key IS NULL)');
        DB::statement('ALTER TABLE store_delivery_zones ADD CONSTRAINT se4_a_check3 CHECK ((fee_usd IS NULL OR fee_usd >= 0) AND (fee_syp IS NULL OR fee_syp >= 0))');
        DB::statement('ALTER TABLE store_delivery_zones ADD CONSTRAINT se4_a_check4 CHECK (sort_order <= 999999999)');
        Schema::create('store_delivery_zones_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se4_revision_unique');
            $table->foreign('record_id', 'se4_record_fk')->references('id')->on('store_delivery_zones')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('governorate', 100);
            $table->string('city', 100)->nullable();
            $table->decimal('fee_usd', 18, 2)->nullable();
            $table->decimal('fee_syp', 18, 2)->nullable();
            $table->unsignedInteger('sort_order');
            $table->string('conditions', 1000)->nullable();
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE store_delivery_zones_revisions ADD CONSTRAINT se4_r_check0 CHECK (version > 0)');
        DB::statement('ALTER TABLE store_delivery_zones_revisions ADD CONSTRAINT se4_r_check1 CHECK ((fee_usd IS NULL OR fee_usd >= 0) AND (fee_syp IS NULL OR fee_syp >= 0))');
        DB::statement('ALTER TABLE store_delivery_zones_revisions ADD CONSTRAINT se4_r_check2 CHECK (sort_order <= 999999999)');
        Schema::table('store_delivery_zones', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se4_published_fk')->references(['record_id', 'version'])->on('store_delivery_zones_revisions')->restrictOnDelete();
        });
        Schema::create('content_banners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se5_list_index');
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->string('alt_text', 250);
            $table->string('link', 500)->nullable();
            $table->unsignedInteger('sort_order');
            $table->string('image_path', 200)->nullable();
            $table->index(['archived_at', 'sort_order', 'id'], 'se5_order_index');
        });
        DB::statement('ALTER TABLE content_banners ADD CONSTRAINT se5_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE content_banners ADD CONSTRAINT se5_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE content_banners ADD CONSTRAINT se5_a_check2 CHECK (singleton_key IS NULL)');
        DB::statement('ALTER TABLE content_banners ADD CONSTRAINT se5_a_check3 CHECK (sort_order <= 999999999)');
        Schema::create('content_banners_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se5_revision_unique');
            $table->foreign('record_id', 'se5_record_fk')->references('id')->on('content_banners')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->string('alt_text', 250);
            $table->string('link', 500)->nullable();
            $table->unsignedInteger('sort_order');
            $table->string('image_path', 200)->nullable();
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE content_banners_revisions ADD CONSTRAINT se5_r_check0 CHECK (version > 0)');
        DB::statement('ALTER TABLE content_banners_revisions ADD CONSTRAINT se5_r_check1 CHECK (sort_order <= 999999999)');
        Schema::table('content_banners', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se5_published_fk')->references(['record_id', 'version'])->on('content_banners_revisions')->restrictOnDelete();
        });
        Schema::table('content_banners_revisions', fn (Blueprint $table) => $table->index('image_path', 'se5_media_index'));
        Schema::create('content_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se6_list_index');
            $table->string('title', 160);
            $table->string('slug', 80);
            $table->unique('slug', 'se6_slug_unique');
            $table->text('body');
            $table->string('seo_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
        });
        DB::statement('ALTER TABLE content_pages ADD CONSTRAINT se6_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE content_pages ADD CONSTRAINT se6_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE content_pages ADD CONSTRAINT se6_a_check2 CHECK (singleton_key IS NULL)');
        Schema::create('content_pages_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se6_revision_unique');
            $table->foreign('record_id', 'se6_record_fk')->references('id')->on('content_pages')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 80);
            $table->text('body');
            $table->string('seo_title', 160)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE content_pages_revisions ADD CONSTRAINT se6_r_check0 CHECK (version > 0)');
        Schema::table('content_pages', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se6_published_fk')->references(['record_id', 'version'])->on('content_pages_revisions')->restrictOnDelete();
        });
        Schema::create('content_faqs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se7_list_index');
            $table->string('question', 300);
            $table->text('answer');
            $table->unsignedInteger('sort_order');
            $table->index(['archived_at', 'sort_order', 'id'], 'se7_order_index');
        });
        DB::statement('ALTER TABLE content_faqs ADD CONSTRAINT se7_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE content_faqs ADD CONSTRAINT se7_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE content_faqs ADD CONSTRAINT se7_a_check2 CHECK (singleton_key IS NULL)');
        DB::statement('ALTER TABLE content_faqs ADD CONSTRAINT se7_a_check3 CHECK (sort_order <= 999999999)');
        Schema::create('content_faqs_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se7_revision_unique');
            $table->foreign('record_id', 'se7_record_fk')->references('id')->on('content_faqs')->restrictOnDelete();
            $table->string('question', 300);
            $table->text('answer');
            $table->unsignedInteger('sort_order');
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE content_faqs_revisions ADD CONSTRAINT se7_r_check0 CHECK (version > 0)');
        DB::statement('ALTER TABLE content_faqs_revisions ADD CONSTRAINT se7_r_check1 CHECK (sort_order <= 999999999)');
        Schema::table('content_faqs', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se7_published_fk')->references(['record_id', 'version'])->on('content_faqs_revisions')->restrictOnDelete();
        });
        Schema::create('content_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('singleton_key')->nullable()->unique();
            $table->unsignedBigInteger('lock_version');
            $table->unsignedBigInteger('published_version')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'created_at', 'id'], 'se8_list_index');
            $table->string('title', 160);
            $table->string('slug', 80);
            $table->unique('slug', 'se8_slug_unique');
            $table->text('body');
        });
        DB::statement('ALTER TABLE content_policies ADD CONSTRAINT se8_a_check0 CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE content_policies ADD CONSTRAINT se8_a_check1 CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE content_policies ADD CONSTRAINT se8_a_check2 CHECK (singleton_key IS NULL)');
        Schema::create('content_policies_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_id');
            $table->unsignedBigInteger('version');
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unique(['record_id', 'version'], 'se8_revision_unique');
            $table->foreign('record_id', 'se8_record_fk')->references('id')->on('content_policies')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 80);
            $table->text('body');
            $table->timestamp('created_at');
        });
        DB::statement('ALTER TABLE content_policies_revisions ADD CONSTRAINT se8_r_check0 CHECK (version > 0)');
        Schema::table('content_policies', function (Blueprint $table): void {
            $table->foreign(['id', 'published_version'], 'se8_published_fk')->references(['record_id', 'version'])->on('content_policies_revisions')->restrictOnDelete();
        });
        Schema::create('store_change_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->enum('target_type', ['store_profiles', 'store_currencies', 'store_payment_policies', 'store_delivery_zones', 'content_banners', 'content_pages', 'content_faqs', 'content_policies']);
            $table->uuid('target_id');
            $table->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $table->unsignedBigInteger('previous_version');
            $table->unsignedBigInteger('new_version');
            $table->json('changed_fields');
            $table->uuid('operation_id');
            $table->char('payload_fingerprint', 64);
            $table->string('request_id', 100);
            $table->timestamp('created_at');
            $table->unique(['owner_id', 'operation_id'], 'store_event_operation_unique');
            $table->index(['target_type', 'target_id', 'created_at', 'id'], 'store_event_target_index');
            $table->index(['created_at', 'id'], 'store_event_time_index');
        });
        DB::statement('ALTER TABLE store_change_events ADD CONSTRAINT store_event_version_check CHECK (new_version > previous_version)');
    }

    public function down(): void
    {
        // Destructive for immutable history. Requires separately authorized backup/retention review.
        Schema::dropIfExists('store_change_events');
        Schema::table('content_policies', fn (Blueprint $table) => $table->dropForeign('se8_published_fk'));
        Schema::dropIfExists('content_policies_revisions');
        Schema::dropIfExists('content_policies');
        Schema::table('content_faqs', fn (Blueprint $table) => $table->dropForeign('se7_published_fk'));
        Schema::dropIfExists('content_faqs_revisions');
        Schema::dropIfExists('content_faqs');
        Schema::table('content_pages', fn (Blueprint $table) => $table->dropForeign('se6_published_fk'));
        Schema::dropIfExists('content_pages_revisions');
        Schema::dropIfExists('content_pages');
        Schema::table('content_banners', fn (Blueprint $table) => $table->dropForeign('se5_published_fk'));
        Schema::dropIfExists('content_banners_revisions');
        Schema::dropIfExists('content_banners');
        Schema::table('store_delivery_zones', fn (Blueprint $table) => $table->dropForeign('se4_published_fk'));
        Schema::dropIfExists('store_delivery_zones_revisions');
        Schema::dropIfExists('store_delivery_zones');
        Schema::table('store_payment_policies', fn (Blueprint $table) => $table->dropForeign('se3_published_fk'));
        Schema::dropIfExists('store_payment_policies_revisions');
        Schema::dropIfExists('store_payment_policies');
        Schema::table('store_currencies', fn (Blueprint $table) => $table->dropForeign('se2_published_fk'));
        Schema::dropIfExists('store_currencies_revisions');
        Schema::dropIfExists('store_currencies');
        Schema::table('store_profiles', fn (Blueprint $table) => $table->dropForeign('se1_published_fk'));
        Schema::dropIfExists('store_profiles_revisions');
        Schema::dropIfExists('store_profiles');
    }
};
