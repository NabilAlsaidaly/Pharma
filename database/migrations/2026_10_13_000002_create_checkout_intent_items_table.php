<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intent_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('checkout_intent_id');
            $table->uuid('source_cart_item_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->integer('quantity');
            $table->boolean('requires_fulfillment_review');
            $table->unsignedSmallInteger('line_order');
            $table->char('selection_key', 36)->storedAs("COALESCE(variant_id, '')");
            $table->unique(['checkout_intent_id', 'product_id', 'selection_key'], 'checkout_items_selection_unique');
            $table->unique(['checkout_intent_id', 'line_order'], 'checkout_items_line_unique');
            $table->unique(['checkout_intent_id', 'source_cart_item_id'], 'checkout_items_source_unique');
            $table->foreign('checkout_intent_id')->references('id')->on('checkout_intents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['variant_id', 'product_id'], 'checkout_items_variant_product_foreign')
                ->references(['id', 'product_id'])->on('product_variants')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement('ALTER TABLE checkout_intent_items ADD CONSTRAINT checkout_items_quantity_check CHECK (quantity BETWEEN 1 AND 2147483647)');
        DB::statement('ALTER TABLE checkout_intent_items ADD CONSTRAINT checkout_items_review_check CHECK (requires_fulfillment_review IN (0,1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intent_items');
    }
};
