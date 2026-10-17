<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_workers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('phone', 40);
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active');
            $table->unsignedBigInteger('lock_version');
            $table->timestamps();
            $table->index(['is_active', 'name', 'id'], 'delivery_worker_directory');
            $table->index(['name', 'id'], 'delivery_worker_names');
        });
        DB::statement('ALTER TABLE delivery_workers ADD CONSTRAINT delivery_worker_version CHECK (lock_version > 0)');
        DB::statement('ALTER TABLE delivery_workers ADD CONSTRAINT delivery_worker_active CHECK (is_active IN (0, 1))');
        Schema::create('delivery_worker_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('worker_id')->constrained('delivery_workers')->restrictOnDelete();
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('payload_fingerprint', 64);
            $table->enum('action', ['save', 'disable', 'enable']);
            $table->unsignedBigInteger('previous_version');
            $table->unsignedBigInteger('new_version');
            $table->json('changed_fields');
            $table->string('request_id', 100);
            $table->timestamp('created_at');
            $table->unique(['owner_id', 'operation_id'], 'delivery_worker_operation');
            $table->unique(['worker_id', 'new_version'], 'delivery_worker_change_version');
            $table->index(['created_at', 'id'], 'delivery_worker_history');
            $table->index(['worker_id', 'created_at', 'id'], 'delivery_worker_timeline');
        });
        DB::statement('ALTER TABLE delivery_worker_changes ADD CONSTRAINT delivery_worker_change_advances CHECK (new_version > previous_version)');
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_worker_changes');
        Schema::dropIfExists('delivery_workers');
    }
};
