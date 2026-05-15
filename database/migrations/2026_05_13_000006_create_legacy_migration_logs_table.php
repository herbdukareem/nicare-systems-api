<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->unsignedBigInteger('legacy_id');
            $table->string('legacy_enrolment_number')->nullable();
            $table->foreignId('new_enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->string('migration_status')->index();
            $table->text('message')->nullable();
            $table->json('legacy_payload')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'legacy_id']);
            $table->index('legacy_enrolment_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_logs');
    }
};
