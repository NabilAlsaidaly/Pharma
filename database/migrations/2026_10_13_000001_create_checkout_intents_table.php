<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('cart_id');
            $table->unsignedBigInteger('source_cart_version');
            $table->uuid('operation_id');
            $table->char('request_fingerprint', 64);
            $table->enum('currency', ['USD', 'SYP']);
            $table->enum('status', ['submitted'])->default('submitted');
            $table->string('full_name', 150);
            $table->string('phone', 40);
            $table->string('email', 255);
            $table->string('governorate', 120);
            $table->string('city', 120);
            $table->string('address', 500);
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'operation_id']);
            $table->index(['customer_id', 'created_at', 'id']);
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('cart_id')->references('id')->on('carts')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intents');
    }
};
