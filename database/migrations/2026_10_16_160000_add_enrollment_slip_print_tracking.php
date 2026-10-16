<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            $table->timestamp('slip_first_generated_at')->nullable();
            $table->timestamp('slip_last_generated_at')->nullable();
            $table->foreignId('slip_last_generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('slip_generation_count')->default(0);
            $table->uuid('slip_last_batch_id')->nullable();
            $table->uuid('slip_reserved_batch_id')->nullable()->index();
            $table->timestamp('slip_reserved_until')->nullable();
            $table->index(['status', 'slip_first_generated_at', 'id'], 'enrollees_slip_queue_index');
        });

        Schema::create('enrollment_slip_print_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('status', 20)->default('preparing');
            $table->json('filters');
            $table->json('parts')->nullable();
            $table->unsignedInteger('enrollee_count');
            $table->unsignedSmallInteger('chunk_size')->default(40);
            // Older MariaDB versions implicitly add ON UPDATE to a first non-null TIMESTAMP.
            $table->dateTime('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('enrollment_slip_print_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('enrollment_slip_print_batches')->cascadeOnDelete();
            $table->foreignId('enrollee_id')->constrained('enrollees')->cascadeOnDelete();
            $table->unsignedInteger('part');
            $table->unique(['batch_id', 'enrollee_id']);
            $table->index(['batch_id', 'part']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_slip_print_items');
        Schema::dropIfExists('enrollment_slip_print_batches');
        Schema::table('enrollees', function (Blueprint $table): void {
            $table->dropForeign(['slip_last_generated_by']);
            $table->dropIndex('enrollees_slip_queue_index');
            $table->dropIndex(['slip_reserved_batch_id']);
            $table->dropColumn(['slip_first_generated_at', 'slip_last_generated_at', 'slip_last_generated_by', 'slip_generation_count', 'slip_last_batch_id', 'slip_reserved_batch_id', 'slip_reserved_until']);
        });
    }
};
