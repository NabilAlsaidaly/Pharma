<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unique(['id', 'product_id'], 'product_variants_id_product_id_unique');
        });
        Schema::create('favorites', function (Blueprint $table): void {
            $table->uuid('customer_id');
            $table->uuid('product_id');
            $table->timestamp('created_at');
            $table->primary(['customer_id', 'product_id']);
            $table->index(['customer_id', 'created_at', 'product_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('carts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->unique();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cart_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->integer('quantity');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->char('selection_key', 36)->storedAs("COALESCE(variant_id, '')");
            $table->unique(['cart_id', 'product_id', 'selection_key'], 'cart_items_selection_unique');
            $table->index(['cart_id', 'created_at', 'id']);
            $table->foreign('cart_id')->references('id')->on('carts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id', 'product_id'], 'cart_items_variant_product_foreign')
                ->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_check CHECK (quantity BETWEEN 1 AND 2147483647)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('favorites');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropUnique('product_variants_id_product_id_unique'));
    }
};
