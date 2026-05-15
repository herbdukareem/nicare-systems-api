<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('benefactors')) {
            Schema::table('benefactors', function (Blueprint $table) {
                if (!Schema::hasColumn('benefactors', 'type')) {
                    $table->string('type')->nullable()->after('name');
                }
                if (!Schema::hasColumn('benefactors', 'registration_number')) {
                    $table->string('registration_number')->nullable()->after('type');
                }
                if (!Schema::hasColumn('benefactors', 'contact_person')) {
                    $table->string('contact_person')->nullable()->after('registration_number');
                }
                if (!Schema::hasColumn('benefactors', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('facilities') && !Schema::hasColumn('facilities', 'accreditation_status')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->enum('accreditation_status', ['active', 'suspended', 'revoked'])->default('active')->after('status');
            });
        }

        if (Schema::hasTable('facilities')) {
            DB::table('facilities')->whereNull('capacity')->update(['capacity' => 0]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('benefactors')) {
            Schema::table('benefactors', function (Blueprint $table) {
                foreach (['created_by', 'contact_person', 'registration_number', 'type'] as $column) {
                    if (!Schema::hasColumn('benefactors', $column)) {
                        continue;
                    }

                    if ($column === 'created_by') {
                        try {
                            $table->dropConstrainedForeignId($column);
                        } catch (Throwable) {
                            $table->dropColumn($column);
                        }
                        continue;
                    }

                    $table->dropColumn($column);
                }
            });
        }

        if (Schema::hasTable('facilities') && Schema::hasColumn('facilities', 'accreditation_status')) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->dropColumn('accreditation_status');
            });
        }
    }
};
