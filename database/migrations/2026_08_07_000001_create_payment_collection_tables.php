<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->morphs('payable');
            $table->string('purpose', 80);
            $table->string('provider', 40)->default('paystack');
            $table->string('collection_mode', 30);
            $table->string('reference', 100)->unique();
            $table->decimal('amount_due', 14, 2);
            $table->decimal('amount_received', 14, 2)->default(0);
            $table->string('currency', 10)->default('NGN');
            $table->string('status', 30)->default('pending');
            $table->json('payer')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payment_collection_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('mode', 30);
            $table->string('payer_key', 191)->nullable();
            $table->string('provider_account_id', 120)->nullable();
            $table->string('provider_reference', 120)->nullable()->unique();
            $table->string('bank_name', 120);
            $table->string('account_name', 191);
            $table->string('account_number', 80);
            $table->string('status', 30)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['provider', 'payer_key', 'status']);
            $table->index(['provider', 'account_number']);
        });

        Schema::create('payment_collection_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 100);
            $table->string('status', 30)->default('received');
            $table->json('payload');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_collection_events');
        Schema::dropIfExists('payment_collection_accounts');
        Schema::dropIfExists('payment_intents');
    }
};
