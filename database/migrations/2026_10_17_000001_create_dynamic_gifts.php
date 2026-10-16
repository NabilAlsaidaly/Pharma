<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_occasions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 160);
            $t->string('description', 500)->nullable();
            $t->boolean('is_active')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->unsignedBigInteger('lock_version');
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['archived_at', 'is_active', 'sort_order', 'id'], 'gift_occasion_listing');
        });
        Schema::create('gift_packages', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('slug', 80)->unique();
            $t->unsignedBigInteger('lock_version');
            $t->unsignedBigInteger('published_version')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['archived_at', 'created_at', 'id'], 'gift_package_listing');
        });
        Schema::create('gift_package_revisions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('package_id')->constrained('gift_packages')->restrictOnDelete();
            $t->unsignedBigInteger('version');
            $t->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $t->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore']);
            $t->string('name', 160);
            $t->string('short_description', 500)->nullable();
            $t->text('description')->nullable();
            $t->string('suitability', 1000)->nullable();
            $t->decimal('price_usd', 18, 2)->nullable();
            $t->decimal('price_syp', 18, 2)->nullable();
            $t->enum('availability', ['available', 'on_request', 'unavailable']);
            $t->unsignedInteger('sort_order');
            $t->timestamp('created_at');
            $t->unique(['package_id', 'version'], 'gift_revision_identity');
            $t->index(['sort_order', 'package_id'], 'gift_revision_order');
        });
        Schema::table('gift_packages', function (Blueprint $t): void {
            $t->foreign(['id', 'published_version'], 'gift_publication_fk')->references(['package_id', 'version'])->on('gift_package_revisions')->restrictOnDelete();
        });
        Schema::create('gift_revision_components', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('revision_id')->constrained('gift_package_revisions')->restrictOnDelete();
            $t->enum('type', ['catalog', 'manual']);
            $t->foreignUuid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $t->uuid('variant_id')->nullable();
            $t->string('name', 255);
            $t->string('variant_name', 255)->nullable();
            $t->string('description', 1000)->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('sort_order');
            $t->string('selection_key', 100);
            $t->foreign(['variant_id', 'product_id'], 'gift_component_variant_fk')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete();
            $t->unique(['revision_id', 'selection_key'], 'gift_component_unique');
            $t->index(['revision_id', 'sort_order', 'id'], 'gift_component_order');
        });
        Schema::create('gift_revision_images', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('revision_id')->constrained('gift_package_revisions')->restrictOnDelete();
            $t->uuid('media_id');
            $t->string('alt_text', 250);
            $t->unsignedInteger('sort_order');
            $t->boolean('is_primary');
            $t->unsignedTinyInteger('primary_slot')->nullable();
            $t->unique(['revision_id', 'media_id'], 'gift_revision_media_unique');
            $t->unique(['revision_id', 'primary_slot'], 'gift_primary_unique');
            $t->index(['media_id', 'revision_id'], 'gift_media_visibility');
        });
        Schema::create('gift_revision_occasions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('revision_id')->constrained('gift_package_revisions')->restrictOnDelete();
            $t->foreignUuid('occasion_id')->constrained('gift_occasions')->restrictOnDelete();
            $t->string('name', 160);
            $t->unique(['revision_id', 'occasion_id'], 'gift_revision_occasion_unique');
            $t->index(['occasion_id', 'revision_id'], 'gift_occasion_filter');
        });
        Schema::create('gift_requests', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $t->uuid('operation_id');
            $t->char('payload_fingerprint', 64);
            $t->uuid('package_id');
            $t->unsignedBigInteger('published_version');
            $t->foreign(['package_id', 'published_version'], 'gift_request_publication_fk')->references(['package_id', 'version'])->on('gift_package_revisions')->restrictOnDelete();
            $t->string('package_name', 160);
            $t->enum('currency', ['USD', 'SYP']);
            $t->decimal('display_price_snapshot', 18, 2);
            $t->enum('status', ['submitted'])->default('submitted');
            $t->boolean('requires_fulfillment_review');
            $t->foreignUuid('occasion_id')->nullable()->constrained('gift_occasions')->restrictOnDelete();
            $t->string('occasion_name', 160)->nullable();
            $t->string('account_email', 255);
            $t->string('sender_display_name', 150)->nullable();
            $t->string('message', 1000)->nullable();
            $t->string('recipient_name', 150);
            $t->string('recipient_phone', 40);
            $t->string('recipient_governorate', 120);
            $t->string('recipient_city', 120);
            $t->string('recipient_address', 500);
            $t->string('recipient_notes', 1000)->nullable();
            $t->timestamp('created_at');
            $t->unique(['customer_id', 'operation_id'], 'gift_customer_operation_unique');
            $t->index(['customer_id', 'created_at', 'id'], 'gift_customer_history');
            $t->index(['created_at', 'id'], 'gift_owner_history');
            $t->index(['currency', 'created_at', 'id'], 'gift_currency_history');
        });
        Schema::create('gift_request_components', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('request_id')->constrained('gift_requests')->restrictOnDelete();
            $t->enum('type', ['catalog', 'manual']);
            $t->foreignUuid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $t->uuid('variant_id')->nullable();
            $t->string('name', 255);
            $t->string('variant_name', 255)->nullable();
            $t->string('description', 1000)->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('sort_order');
            $t->foreign(['variant_id', 'product_id'], 'gift_request_variant_fk')->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete();
            $t->index(['request_id', 'sort_order', 'id'], 'gift_request_component_order');
        });
        Schema::create('gift_request_reviews', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('request_id')->unique()->constrained('gift_requests')->restrictOnDelete();
            $t->enum('review_status', ['new', 'under_review', 'awaiting_contact', 'reviewed']);
            $t->unsignedBigInteger('lock_version');
            $t->timestamp('review_started_at')->nullable();
            $t->timestamp('review_completed_at')->nullable();
            $t->timestamp('last_activity_at');
            $t->foreignUuid('last_owner_id')->nullable()->constrained('management_users')->restrictOnDelete();
            $t->unique(['id', 'request_id'], 'gift_review_identity');
            $t->index(['review_status', 'request_id'], 'gift_review_filter');
        });
        Schema::create('gift_review_events', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('request_id');
            $t->uuid('review_id');
            $t->foreign(['review_id', 'request_id'], 'gift_event_review_fk')->references(['id', 'request_id'])->on('gift_request_reviews')->restrictOnDelete();
            $t->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $t->enum('kind', ['transition', 'note', 'follow_up']);
            $t->enum('previous_status', ['new', 'under_review', 'awaiting_contact', 'reviewed']);
            $t->enum('new_status', ['new', 'under_review', 'awaiting_contact', 'reviewed']);
            $t->string('note', 2000)->nullable();
            $t->unsignedBigInteger('resulting_version');
            $t->timestamp('created_at');
            $t->index(['request_id', 'created_at', 'id'], 'gift_event_timeline');
        });
        Schema::create('gift_change_events', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $t->enum('target_type', ['package', 'occasion', 'review']);
            $t->uuid('target_id');
            $t->enum('action', ['save', 'media', 'publish', 'unpublish', 'archive', 'restore', 'transition', 'note', 'follow_up']);
            $t->unsignedBigInteger('previous_version');
            $t->unsignedBigInteger('new_version');
            $t->json('changed_fields');
            $t->uuid('operation_id');
            $t->char('payload_fingerprint', 64);
            $t->string('request_id', 100);
            $t->timestamp('created_at');
            $t->unique(['owner_id', 'operation_id'], 'gift_owner_operation_unique');
            $t->index(['created_at', 'id'], 'gift_audit_time');
            $t->index(['target_type', 'target_id', 'created_at', 'id'], 'gift_audit_target');
        });
        foreach (['gift_occasions', 'gift_packages', 'gift_request_reviews'] as $table) {
            DB::statement("ALTER TABLE $table ADD CHECK (lock_version > 0)");
        }
        DB::statement('ALTER TABLE gift_packages ADD CHECK (archived_at IS NULL OR published_version IS NULL)');
        DB::statement('ALTER TABLE gift_package_revisions ADD CHECK (version > 0), ADD CHECK (price_usd IS NULL OR price_usd >= 0), ADD CHECK (price_syp IS NULL OR price_syp >= 0)');
        foreach (['gift_revision_components', 'gift_request_components'] as $table) {
            DB::statement("ALTER TABLE $table ADD CHECK (quantity BETWEEN 1 AND 100), ADD CHECK ((type = 'manual' AND product_id IS NULL AND variant_id IS NULL AND variant_name IS NULL) OR (type = 'catalog' AND product_id IS NOT NULL))");
        }
        DB::statement('ALTER TABLE gift_requests ADD CHECK (display_price_snapshot > 0)');
        DB::statement('ALTER TABLE gift_revision_images ADD CHECK ((is_primary = 1 AND primary_slot IS NOT NULL AND primary_slot = 1) OR (is_primary = 0 AND primary_slot IS NULL))');
        foreach (['gift_occasions', 'gift_package_revisions', 'gift_revision_components', 'gift_revision_images', 'gift_request_components'] as $table) {
            DB::statement("ALTER TABLE $table ADD CHECK (sort_order <= 999999999)");
        }
        DB::statement('ALTER TABLE gift_change_events ADD CHECK (new_version = previous_version + 1)');
        DB::statement('ALTER TABLE gift_review_events ADD CHECK (resulting_version > 0)');
        DB::statement('ALTER TABLE gift_requests DROP INDEX gift_customer_history, ADD INDEX gift_customer_history (customer_id, created_at DESC, id ASC), DROP INDEX gift_owner_history, ADD INDEX gift_owner_history (created_at DESC, id ASC), DROP INDEX gift_currency_history, ADD INDEX gift_currency_history (currency, created_at DESC, id ASC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_change_events');
        Schema::dropIfExists('gift_review_events');
        Schema::dropIfExists('gift_request_reviews');
        Schema::dropIfExists('gift_request_components');
        Schema::dropIfExists('gift_requests');
        Schema::dropIfExists('gift_revision_occasions');
        Schema::dropIfExists('gift_revision_images');
        Schema::dropIfExists('gift_revision_components');
        Schema::table('gift_packages', fn (Blueprint $t) => $t->dropForeign('gift_publication_fk'));
        Schema::dropIfExists('gift_package_revisions');
        Schema::dropIfExists('gift_packages');
        Schema::dropIfExists('gift_occasions');
    }
};
