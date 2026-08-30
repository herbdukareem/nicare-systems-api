<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\OfficerDevice;
use App\Services\OfficerDeviceService;
use Illuminate\Http\Request;

class OfficerDeviceController extends BaseController
{
    public function __construct(private OfficerDeviceService $service)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $devices = OfficerDevice::with('user:id,name,username,email')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";

                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('device_name', 'like', $like)
                        ->orWhere('device_uuid', 'like', $like)
                        ->orWhere('platform', 'like', $like)
                        ->orWhere('app_version', 'like', $like)
                        ->orWhereHas('user', function ($userQuery) use ($like) {
                            $userQuery
                                ->where('name', 'like', $like)
                                ->orWhere('username', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->latest('last_seen_at')
            ->paginate($request->integer('per_page', 20));

        return $this->sendResponse($devices, 'Officer devices retrieved.');
    }

    public function revoke(OfficerDevice $device)
    {
        return $this->sendResponse($this->service->revoke($device), 'Officer device revoked.');
    }
}
