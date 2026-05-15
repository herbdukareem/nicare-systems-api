<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('enrollee_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_programme_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::table('benefactors', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            $table->string('registration_number')->nullable()->after('type');
            $table->string('contact_person')->nullable()->after('registration_number');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->foreignId('benefactor_id')->nullable()->constrained('benefactors')->nullOnDelete();
            $table->string('registration_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('premium_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('benefit_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('waiting_period_days')->default(0);
            $table->boolean('is_family_plan')->default(false);
            $table->unsignedInteger('maximum_dependants')->default(0);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('premium_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('premium_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('benefactor_id')->nullable()->constrained('benefactors')->nullOnDelete();
            $table->foreignId('funding_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('payer_type');
            $table->string('payer_name');
            $table->string('payer_phone')->nullable();
            $table->string('payer_email')->nullable();
            $table->json('payer_details')->nullable();
            $table->string('payment_method');
            $table->string('payment_status')->default('pending');
            $table->string('payment_reference')->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 14, 2);
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('premium_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('premium_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('premium_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_code')->index();
            $table->string('pin')->unique();
            $table->string('serial_number')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('generated')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_enrollee_id')->nullable()->constrained('enrollees')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('benefactor_enrollees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('benefactor_id')->constrained('benefactors')->cascadeOnDelete();
            $table->foreignId('enrollee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('premium_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relationship')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['benefactor_id', 'enrollee_id']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('enrollee_id')->constrained()->cascadeOnDelete();
            $table->string('member_number')->nullable();
            $table->string('role')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['group_id', 'enrollee_id']);
        });

        Schema::create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->string('employer_name');
            $table->foreignId('benefactor_id')->nullable()->constrained('benefactors')->nullOnDelete();
            $table->foreignId('funding_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('insurance_programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollee_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('premium_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status')->default('uploaded');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_batch_enrollees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('staff_number')->nullable();
            $table->string('nin')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('employee_contribution', 14, 2)->default(0);
            $table->decimal('employer_contribution', 14, 2)->default(0);
            $table->string('status')->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('subsidy_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->string('funding_source');
            $table->foreignId('benefactor_id')->nullable()->constrained('benefactors')->nullOnDelete();
            $table->foreignId('funding_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('insurance_programme_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollee_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('premium_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->date('coverage_start_date');
            $table->date('coverage_end_date');
            $table->string('status')->default('uploaded');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subsidy_batch_enrollees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidy_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nin')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vulnerability_type');
            $table->boolean('vulnerability_verified')->default(false);
            $table->string('status')->default('pending');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidy_batch_enrollees');
        Schema::dropIfExists('subsidy_batches');
        Schema::dropIfExists('payroll_batch_enrollees');
        Schema::dropIfExists('payroll_batches');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('benefactor_enrollees');
        Schema::dropIfExists('premium_pins');
        Schema::dropIfExists('premium_purchases');
        Schema::dropIfExists('premium_plans');
        Schema::dropIfExists('groups');
        Schema::table('benefactors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['type', 'registration_number', 'contact_person']);
        });
        Schema::dropIfExists('enrollee_categories');
        Schema::dropIfExists('insurance_programmes');
    }
};
