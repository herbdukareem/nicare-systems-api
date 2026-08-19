<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_PERMISSION = 'capitation.export';
    private const ENROLLEE_LIST_PERMISSION = 'capitation.enrollee-list.view';

    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $existing = DB::table('permissions')
            ->where('name', self::ENROLLEE_LIST_PERMISSION)
            ->first();

        $attributes = [
            'label' => 'Capitation Enrollee List View',
            'description' => 'Allows capitation enrollee list access and export.',
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('permissions', 'category')) {
            $attributes['category'] = 'Capitation & Payments';
        }

        if ($existing) {
            DB::table('permissions')
                ->where('id', $existing->id)
                ->update($attributes);
        } else {
            DB::table('permissions')->insert(array_merge($attributes, [
                'name' => self::ENROLLEE_LIST_PERMISSION,
                'created_at' => $now,
            ]));
        }

        $newPermissionId = DB::table('permissions')
            ->where('name', self::ENROLLEE_LIST_PERMISSION)
            ->value('id');

        $legacyPermissionId = DB::table('permissions')
            ->where('name', self::LEGACY_PERMISSION)
            ->value('id');

        if (!$newPermissionId || !$legacyPermissionId) {
            return;
        }

        $roleIds = DB::table('permission_role')
            ->where('permission_id', $legacyPermissionId)
            ->pluck('role_id')
            ->unique()
            ->values();

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')->insertOrIgnore(
            $roleIds->map(fn ($roleId) => [
                'permission_id' => $newPermissionId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', self::ENROLLEE_LIST_PERMISSION)
            ->value('id');

        if (!$permissionId) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->delete();

        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
