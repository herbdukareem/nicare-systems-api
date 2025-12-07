<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drug_details', function (Blueprint $table) {
            $table->id();
            $table->string('generic_name')->comment('Generic/scientific name of the drug');
            $table->string('brand_name')->nullable()->comment('Brand/trade name');
            $table->string('dosage_form')->nullable()->comment('e.g., Tablet, Capsule, Syrup, Injection');
            $table->string('strength')->nullable()->comment('e.g., 500mg, 250mg/5ml');
            $table->string('route_of_administration')->nullable()->comment('e.g., Oral, IV, IM, Topical');
            $table->string('manufacturer')->nullable()->comment('Drug manufacturer');
            $table->string('drug_class')->nullable()->comment('Therapeutic class (e.g., Antibiotic, Analgesic)');
            $table->text('indications')->nullable()->comment('What the drug is used for');
            $table->text('contraindications')->nullable()->comment('When not to use the drug');
            $table->text('side_effects')->nullable()->comment('Common side effects');
            $table->string('storage_conditions')->nullable()->comment('How to store the drug');
            $table->boolean('prescription_required')->default(true)->comment('Whether prescription is required');
            $table->boolean('controlled_substance')->default(false)->comment('Whether it is a controlled substance');
            $table->string('nafdac_number')->nullable()->comment('NAFDAC registration number');
            $table->date('expiry_date')->nullable()->comment('Expiry date if applicable');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('generic_name');
            $table->index('brand_name');
            $table->index('drug_class');
            $table->index('prescription_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_details');
    }
};

