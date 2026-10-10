<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('logo_path', 2048)->nullable();
            $t->text('description')->nullable();
            $t->string('status', 16)->default('hidden');
            $t->unsignedInteger('sort_order')->default(0);
            $t->string('meta_title')->nullable();
            $t->text('meta_description')->nullable();
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->index(['status', 'sort_order', 'id']);
        });
        Schema::create('products', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('brand_id')->constrained('brands')->restrictOnDelete()->restrictOnUpdate();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('short_description')->nullable();
            $t->longText('full_description')->nullable();
            $t->string('status', 16)->default('hidden');
            $t->string('availability_status', 16)->default('unavailable');
            $t->boolean('is_recommended')->default(false);
            foreach (['usage_instructions', 'suitable_for', 'benefits'] as $column) {
                $t->text($column)->nullable();
            }
            $t->decimal('price_usd', 18, 2)->nullable();
            $t->decimal('price_syp', 18, 2)->nullable();
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->index(['status', 'created_at', 'id']);
            $t->index(['brand_id', 'status', 'created_at', 'id']);
            $t->index(['status', 'is_recommended', 'created_at', 'id']);
        });
        Schema::create('product_variants', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $t->string('name');
            $t->decimal('price_usd', 18, 2)->nullable();
            $t->decimal('price_syp', 18, 2)->nullable();
            $t->string('availability_status', 16)->default('unavailable');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->index(['product_id', 'sort_order', 'id']);
        });
        Schema::create('product_images', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $t->string('path', 2048);
            $t->boolean('is_primary')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->char('primary_product_id', 36)->nullable()->storedAs('CASE WHEN is_primary = 1 THEN product_id ELSE NULL END')->unique();
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->index(['product_id', 'sort_order', 'id']);
        });
        Schema::create('concerns', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('status', 16)->default('hidden');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->index(['status', 'sort_order', 'id']);
        });
        Schema::create('concern_product', function (Blueprint $t): void {
            $t->foreignUuid('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $t->foreignUuid('concern_id')->constrained('concerns')->restrictOnDelete()->restrictOnUpdate();
            $t->primary(['product_id', 'concern_id']);
            $t->index(['concern_id', 'product_id']);
        });
        foreach (['brands', 'products', 'concerns'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ('active','hidden'))");
        }
        foreach (['products', 'product_variants'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_availability_check CHECK (availability_status IN ('available','on_request','unavailable'))");
            foreach (['price_usd', 'price_syp'] as $price) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$price}_check CHECK ({$price} IS NULL OR {$price} >= 0)");
            }
        }
        DB::statement('ALTER TABLE product_images ADD CONSTRAINT product_images_primary_check CHECK (is_primary IN (0,1))');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_recommended_check CHECK (is_recommended IN (0,1))');
    }

    public function down(): void
    {
        foreach (['concern_product', 'concerns', 'product_images', 'product_variants', 'products', 'brands'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
