<?php

use App\Models\Enrollee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nin_verification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('nin_hash', 64)->index();
            $table->string('provider_name')->nullable()->index();
            $table->string('channel', 40)->default('enrollee');
            $table->string('status', 24)->index();
            $table->boolean('is_provider_request')->default(true)->index();
            $table->boolean('is_reconstructed')->default(false)->index();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->string('source_record_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('attempted_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['provider_name', 'attempted_at'], 'nin_attempts_provider_date_index');
            $table->index(['status', 'attempted_at'], 'nin_attempts_status_date_index');
        });

        $this->backfillExistingVerificationEvidence();
    }

    public function down(): void
    {
        Schema::dropIfExists('nin_verification_attempts');
    }

    private function backfillExistingVerificationEvidence(): void
    {
        if (Schema::hasTable('nin_verification_caches')) {
            DB::table('nin_verification_caches')->orderBy('id')->chunkById(500, function ($caches): void {
                $rows = $caches->map(function ($cache): array {
                    $occurredAt = $cache->verified_at ?: $cache->created_at ?: now();

                    return [
                        'nin_hash' => hash('sha256', (string) $cache->nin),
                        'provider_name' => $cache->provider_name,
                        'channel' => 'historical_cache',
                        'status' => 'succeeded',
                        'is_provider_request' => true,
                        'is_reconstructed' => true,
                        'source_record_key' => 'cache:' . $cache->id,
                        'metadata' => json_encode(['reconstruction_source' => 'nin_verification_caches']),
                        'attempted_at' => $occurredAt,
                        'completed_at' => $occurredAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                DB::table('nin_verification_attempts')->insertOrIgnore($rows);
            });
        }

        if (!Schema::hasTable('enrollees')) {
            return;
        }

        DB::table('enrollees')
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_FAILED)
            ->whereNotNull('nin')
            ->where('nin', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($enrollees): void {
                $rows = $enrollees->map(function ($enrollee): array {
                    $occurredAt = $enrollee->nin_verified_at ?: $enrollee->updated_at ?: now();

                    return [
                        'enrollee_id' => $enrollee->id,
                        'user_id' => $enrollee->nin_verified_by,
                        'nin_hash' => hash('sha256', (string) $enrollee->nin),
                        'provider_name' => $enrollee->nin_verification_provider,
                        'channel' => 'historical_enrollee',
                        'status' => 'failed',
                        'is_provider_request' => true,
                        'is_reconstructed' => true,
                        'source_record_key' => 'enrollee-failure:' . $enrollee->id,
                        'metadata' => json_encode(['reconstruction_source' => 'enrollees.nin_verification_status']),
                        'attempted_at' => $occurredAt,
                        'completed_at' => $occurredAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->all();

                DB::table('nin_verification_attempts')->insertOrIgnore($rows);
            });
    }
};
