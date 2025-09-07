<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_catgories', function (Blueprint $table) {
           $table->id();
           $table->string('name'); 
           $table->string('code')->unique(); // pin, capitable
           $table->text('description')->nullable();
           $table->string('invoice_type'); // Cr=Credit Or Dr=Debit
           $table->timestamps();

        });

        $payment_catgories = [
            ['name' => 'Subscription', 'code' => 'subscription', 'invoice_type' => 'cr'],
            ['name' => 'Re-subscription', 'code' => 're-subscription', 'invoice_type' => 'cr'],
            ['name' => 'Capitation Payment', 'code' => 'capitation', 'invoice_type' => 'dr'], 
        ];

        foreach ($payment_catgories as $payment_catgory) {
            DB::table('payment_catgories')->insert($payment_catgory);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_catgories');
    }
};