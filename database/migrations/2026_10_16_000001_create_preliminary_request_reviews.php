<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_intents', fn (Blueprint $table) => $table->index(['created_at', 'id'], 'checkout_owner_created_id_index'));
        Schema::create('preliminary_request_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('checkout_intent_id')->unique('review_request_unique')->constrained('checkout_intents')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('review_status', ['new', 'under_review', 'awaiting_contact', 'reviewed'])->default('new');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->foreignUuid('last_reviewed_by_owner_id')->nullable()->constrained('management_users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('review_completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'checkout_intent_id'], 'review_id_request_unique');
            $table->index(['review_status', 'checkout_intent_id'], 'review_status_request_index');
            $table->index(['last_activity_at', 'checkout_intent_id'], 'review_activity_request_index');
        });
        Schema::create('preliminary_request_review_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('checkout_intent_id')->constrained('checkout_intents')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('review_id');
            $table->foreign(['review_id', 'checkout_intent_id'], 'event_review_request_fk')->references(['id', 'checkout_intent_id'])->on('preliminary_request_reviews')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignUuid('owner_id')->constrained('management_users')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('event_type', ['note_added', 'follow_up_recorded', 'review_started', 'status_changed', 'review_completed', 'review_reopened']);
            $table->enum('previous_status', ['new', 'under_review', 'awaiting_contact', 'reviewed']);
            $table->enum('new_status', ['new', 'under_review', 'awaiting_contact', 'reviewed']);
            $table->string('note', 2000)->nullable();
            $table->uuid('operation_id');
            $table->char('payload_fingerprint', 64);
            $table->unsignedBigInteger('resulting_version');
            $table->uuid('bulk_id')->nullable();
            $table->string('request_id', 100);
            $table->timestamp('created_at');
            $table->unique(['owner_id', 'operation_id'], 'review_owner_operation_unique');
            $table->index(['checkout_intent_id', 'created_at', 'id'], 'review_event_request_time_index');
            $table->index(['created_at', 'checkout_intent_id'], 'review_event_time_request_index');
            $table->index(['bulk_id', 'id'], 'review_event_bulk_index');
        });
    }

    public function down(): void
    {
        // Destructive for review/audit history: rollback needs an explicit operational backup decision.
        Schema::dropIfExists('preliminary_request_review_events');
        Schema::dropIfExists('preliminary_request_reviews');
        Schema::table('checkout_intents', fn (Blueprint $table) => $table->dropIndex('checkout_owner_created_id_index'));
    }
};
