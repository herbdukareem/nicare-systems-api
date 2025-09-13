<?php

use App\Models\PremiumType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_types', function (Blueprint $table) {
            $table->id();
            $table->string('name',100); 
            $table->string('code')->unique();
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });

        // create premium types

                $premium_types = [
                    'individual' => ['premium_amount' => 16100],
                    'household' => ['premium_amount' => 43200],
                ];

                foreach ($premium_types as $type => $premium) {
                   PremiumType::create(
                        [
                            'name' => ucfirst($type),
                            'code' => $type,
                            'amount' => $premium['premium_amount'],
                        ]
                    );

                 
                }
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_types');
    }
};