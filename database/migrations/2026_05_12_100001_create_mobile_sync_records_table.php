<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_sync_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sync_batch_id')->index();
            $table->string('device_id');
            $table->foreignId('officer_user_id')->constrained('users')->onDelete('restrict');
            $table->json('payload');
            $table->enum('status', ['pending', 'processing', 'synced', 'failed', 'duplicate'])->default('pending');
            $table->foreignId('enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->foreignId('duplicate_of_enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sync_records');
    }
};
