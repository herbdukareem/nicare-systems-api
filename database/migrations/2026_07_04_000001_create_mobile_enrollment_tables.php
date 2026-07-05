<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_form_schemas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('channel', 40)->default('mobile');
            $table->foreignId('insurance_programme_id')->nullable()->constrained('insurance_programmes')->nullOnDelete();
            $table->foreignId('enrollee_category_id')->nullable()->constrained('enrollee_categories')->nullOnDelete();
            $table->foreignId('premium_plan_id')->nullable()->constrained('premium_plans')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived', 'revoked'])->default('draft');
            $table->boolean('requires_nin_verification')->default(false);
            $table->boolean('allow_offline_capture')->default(true);
            $table->json('fields');
            $table->json('rules')->nullable();
            $table->json('ui_schema')->nullable();
            $table->json('migration_hints')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel', 'status']);
            $table->index(['insurance_programme_id', 'enrollee_category_id', 'premium_plan_id'], 'enrollment_schema_scope_idx');
        });

        Schema::create('officer_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uuid');
            $table->string('device_name')->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid']);
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('mobile_enrollment_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('sync_batch_id')->index();
            $table->string('client_record_id');
            $table->foreignId('officer_device_id')->constrained('officer_devices')->cascadeOnDelete();
            $table->foreignId('officer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('enrollment_form_schema_id')->nullable()->constrained('enrollment_form_schemas')->nullOnDelete();
            $table->unsignedInteger('schema_version')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->enum('status', [
                'received',
                'pending_nin',
                'nin_failed',
                'duplicate_suspected',
                'pending_approval',
                'approved',
                'rejected',
                'sync_failed',
            ])->default('received');
            $table->text('status_reason')->nullable();
            $table->json('payload');
            $table->json('core_data')->nullable();
            $table->json('extra_fields')->nullable();
            $table->json('migration_hints')->nullable();
            $table->foreignId('enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->foreignId('duplicate_of_enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->timestamp('nin_verified_at')->nullable();
            $table->string('attachment_status', 40)->default('not_uploaded');
            $table->string('app_version', 40)->nullable();
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['officer_device_id', 'client_record_id'], 'mobile_enrollment_device_client_unique');
            $table->index(['officer_user_id', 'status']);
        });

        Schema::create('mobile_enrollment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mobile_enrollment_record_id');
            $table->foreignId('enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->string('kind', 40)->default('passport');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status', 40)->default('uploaded');
            $table->text('failure_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('mobile_enrollment_record_id', 'mobile_attach_record_fk')
                ->references('id')
                ->on('mobile_enrollment_records')
                ->cascadeOnDelete();
        });

        Schema::table('enrollees', function (Blueprint $table): void {
            if (!Schema::hasColumn('enrollees', 'enrollment_extra_fields')) {
                $table->json('enrollment_extra_fields')->nullable()->after('enrollment_source');
            }

            if (!Schema::hasColumn('enrollees', 'enrollment_form_schema_id')) {
                $table->foreignId('enrollment_form_schema_id')
                    ->nullable()
                    ->after('enrollment_extra_fields')
                    ->constrained('enrollment_form_schemas')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('enrollees', 'enrollment_schema_version')) {
                $table->unsignedInteger('enrollment_schema_version')->nullable()->after('enrollment_form_schema_id');
            }

            if (!Schema::hasColumn('enrollees', 'mobile_enrollment_record_id')) {
                $table->foreignId('mobile_enrollment_record_id')
                    ->nullable()
                    ->after('enrollment_schema_version')
                    ->constrained('mobile_enrollment_records')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            if (Schema::hasColumn('enrollees', 'mobile_enrollment_record_id')) {
                $table->dropConstrainedForeignId('mobile_enrollment_record_id');
            }
            if (Schema::hasColumn('enrollees', 'enrollment_schema_version')) {
                $table->dropColumn('enrollment_schema_version');
            }
            if (Schema::hasColumn('enrollees', 'enrollment_form_schema_id')) {
                $table->dropConstrainedForeignId('enrollment_form_schema_id');
            }
            if (Schema::hasColumn('enrollees', 'enrollment_extra_fields')) {
                $table->dropColumn('enrollment_extra_fields');
            }
        });

        Schema::dropIfExists('mobile_enrollment_attachments');
        Schema::dropIfExists('mobile_enrollment_records');
        Schema::dropIfExists('officer_devices');
        Schema::dropIfExists('enrollment_form_schemas');
    }
};
