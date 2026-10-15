<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'brands', 'concerns', 'product_variants', 'product_images'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->softDeletes();
                $table->unsignedBigInteger('lock_version')->default(1);
                $table->index(['deleted_at', 'id']);
            });
        }
        Schema::create('catalog_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('entity_type', 24);
            $table->uuid('entity_id');
            $table->string('action', 32);
            $table->string('request_id', 100);
            $table->uuid('bulk_id')->nullable();
            $table->json('before_values');
            $table->json('after_values');
            $table->timestamp('created_at');
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['bulk_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_audit_events');
        foreach (['product_images', 'product_variants', 'products', 'concerns', 'brands'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['deleted_at', 'id']);
                $table->dropColumn(['deleted_at', 'lock_version']);
            });
        }
    }
};
