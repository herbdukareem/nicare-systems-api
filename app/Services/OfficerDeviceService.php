<?php

namespace App\Services;

use App\Models\OfficerDevice;
use App\Models\User;
use RuntimeException;

class OfficerDeviceService
{
    public function register(User $user, array $data): OfficerDevice
    {
        if (!$user->mobileEnrollmentEnabled()) {
            throw new RuntimeException('Mobile enrollment has been disabled for this officer.');
        }

        $device = OfficerDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_uuid' => $data['device_uuid'],
            ],
            [
                'device_name' => $data['device_name'] ?? null,
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        if ($device->isRevoked()) {
            throw new RuntimeException('This mobile device has been revoked. Contact an administrator to re-enable access.');
        }

        return $device;
    }

    public function activeDeviceFor(User $user, string $deviceUuid): OfficerDevice
    {
        if (!$user->mobileEnrollmentEnabled()) {
            throw new RuntimeException('Mobile enrollment has been disabled for this officer.');
        }

        $device = OfficerDevice::where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->firstOrFail();

        if ($device->isRevoked()) {
            throw new RuntimeException('This mobile device has been revoked.');
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return $device;
    }

    public function revoke(OfficerDevice $device): OfficerDevice
    {
        $device->forceFill(['revoked_at' => now()])->save();
        $device->user?->tokens()?->delete();

        return $device->fresh(['user']);
    }
}
