<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('duplicate_nin_verification_decisions');
        Schema::dropIfExists('duplicate_nin_verification_candidates');
        Schema::dropIfExists('duplicate_nin_verification_items');
        Schema::dropIfExists('duplicate_nin_verification_batches');

        Schema::create('duplicate_nin_verification_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('unique_nin_count')->default(0);
            $table->unsignedInteger('total_candidate_count')->default(0);
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('duplicate_nin_verification_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('duplicate_nin_verification_batches')->cascadeOnDelete();
            $table->string('nin', 32);
            $table->char('nin_hash', 64)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('provider_name')->nullable();
            $table->json('provider_data')->nullable();
            $table->json('comparison_summary')->nullable();
            $table->foreignId('matched_enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->string('decision_source', 24)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'nin'], 'duplicate_nin_items_batch_nin_unique');
        });

        Schema::create('duplicate_nin_verification_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('duplicate_nin_verification_items')->cascadeOnDelete();
            $table->foreignId('enrollee_id')->constrained('enrollees')->cascadeOnDelete();
            $table->string('enrollee_code')->nullable();
            $table->string('full_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('original_nin', 32)->nullable();
            $table->string('status_before', 32)->nullable();
            $table->unsignedTinyInteger('match_score')->default(0);
            $table->json('match_result')->nullable();
            $table->boolean('keeps_nin')->default(false)->index();
            $table->boolean('nin_cleared')->default(false)->index();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'enrollee_id'], 'duplicate_nin_candidates_item_enrollee_unique');
        });

        Schema::create('duplicate_nin_verification_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('duplicate_nin_verification_batches')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('duplicate_nin_verification_items')->cascadeOnDelete();
            $table->string('action', 40)->index();
            $table->foreignId('selected_enrollee_id')->nullable();
            $table->foreignId('previous_selected_enrollee_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('provider_data')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamps();

            $table->foreign('selected_enrollee_id', 'dup_nin_decisions_selected_fk')
                ->references('id')
                ->on('enrollees')
                ->nullOnDelete();
            $table->foreign('previous_selected_enrollee_id', 'dup_nin_decisions_previous_fk')
                ->references('id')
                ->on('enrollees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_nin_verification_decisions');
        Schema::dropIfExists('duplicate_nin_verification_candidates');
        Schema::dropIfExists('duplicate_nin_verification_items');
        Schema::dropIfExists('duplicate_nin_verification_batches');
    }
};
