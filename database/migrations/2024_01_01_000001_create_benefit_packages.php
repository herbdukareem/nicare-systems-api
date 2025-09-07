<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('benefit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
             $table->unsignedSmallInteger('status')->default(1);
            $table->timestamps();
        });

        $benefit_packages = [
            ['name' => 'Standard Package', 'code' => 'standard'],
            ['name' => 'BHCPF (BMPHS)', 'code' => 'BMPHS']
        ];

        foreach ($benefit_packages as $benefit_package) {
            DB::table('benefit_packages')->insert($benefit_package);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_packages');
    }
};
