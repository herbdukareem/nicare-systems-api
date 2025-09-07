<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollees', function (Blueprint $table) {
            $table->id();
            $table->string('enrollee_id', 20);
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->string('legacy_enrollee_id', 20)->nullable();
            $table->string('nin')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->unsignedTinyInteger('sex')->nullable();
            $table->unsignedTinyInteger('marital_status')->nullable()->comment('1=S,2=M,3-D,4=W');
            $table->date('date_of_birth')->nullable();
            $table->string('imgage_url')->nullable();

            // contact
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('village')->nullable();
            
        
            $table->foreignId('sector_id')->nullable();
             $table->foreignId('vulnerable_group_id')->nullable()->default(null); 
             $table->unsignedTinyInteger('relationship_to_principal')->default(1)
             ->comment('1=princiapl,2=spouse,3=child,4=other');
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->constrained();

            $table->foreignId('enrollment_phase_id')->nullable()->constrained();
            
            $table->foreignId('premium_id')->nullable()->constrained('premiums');
            $table->foreignId('funding_type_id')->nullable()->constrained();
            $table->foreignId('benefactor_id')->nullable()->constrained();
            $table->date('capitation_start_date')->nullable();
            $table->timestamp('approval_date')->nullable();
            
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            // nok
            $table->string('nok_name')->nullable();
            $table->string('nok_phone_number')->nullable();
            $table->string('nok_address')->nullable();
            $table->string('nok_relationship')->nullable();

            // employment details
            $table->string('occupation')->nullable();
            $table->string('cno', 10)->nullable();
            $table->date('dfa')->nullable();
            $table->date('dpa')->nullable();
            $table->decimal('basic_salary', 10, 2)->nullable();
            $table->foreignId('mda_id')->nullable()->constrained();

            $table->unsignedTinyInteger('status')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['legacy_id', 'legacy_enrollee_id']);
            // index facility, 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollees');
    }
};