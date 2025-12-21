<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icdcodes', function (Blueprint $table) {
            $table->id();

            // UniqID in file (e.g. 000001)
            $table->string('code', 32)->unique();

            // From file headers
            $table->text('icd10_description')->nullable();
            $table->text('icd9_description')->nullable();

            // Optional inferred hierarchy
            $table->string('parent_code', 32)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icdcodes');
    }
};
