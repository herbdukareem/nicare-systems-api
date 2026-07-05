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
        $devices = OfficerDevice::with('user:id,name,username,email')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest('last_seen_at')
            ->paginate($request->integer('per_page', 20));

        return $this->sendResponse($devices, 'Officer devices retrieved.');
    }

    public function revoke(OfficerDevice $device)
    {
        return $this->sendResponse($this->service->revoke($device), 'Officer device revoked.');
    }
}
