<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Class UserController
 *
 * Handles CRUD operations for users and role management.
 */
class UserController extends BaseController
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['name', 'email', 'status', 'search']);
        $perPage = (int) $request->get('per_page', 15);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $users = $this->userService->paginate($filters, $perPage, $sortBy, $sortDirection);

        // total user, active user, pending user, suspended user
        $stats = [
            'total' => $users->total(),
            'active' => $users->filter(function ($user) {
                return $user->status === 1;
            })->count(),
            'pending' => $users->filter(function ($user) {
                return $user->status === 0;
            })->count(),
            'suspended' => $users->filter(function ($user) {
                return $user->status === 2;
            })->count(),
        ];
   
        $response = UserResource::collection($users);
        $response->additional([
            'stats' => $stats,
            'meta' => [
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ],
        ]);

        return $this->sendResponse($response, 'Users retrieved successfully');
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        // Remove password confirmation from data
        unset($data['password_confirmation']);

        // Handle roles separately
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = $this->userService->create($data);

        // Assign roles if provided
        if (!empty($roles)) {
            $user->roles()->sync($roles);
        }

        return $this->sendResponse(new UserResource($user->load('roles')), 'User created successfully', 201);
    }

    /**
     * Display the specified user with roles and permissions.
     */
    public function show(User $user)
    {
        $user->load('roles', 'roles.permissions', 'userable');
        return $this->sendResponse(new UserResource($user), 'User retrieved successfully');
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // Handle password update
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        // Remove password confirmation from data
        unset($data['password_confirmation']);

        // Handle roles separately if provided
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user->update($data);

        // Sync roles if provided
        if ($roles !== null) {
            $user->roles()->sync($roles);
        }

        return $this->sendResponse(new UserResource($user->load('roles')), 'User updated successfully');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $user->delete();
        return $this->sendResponse([], 'User deleted successfully');
    }

    /**
     * Assign roles to a user.
     */
    public function syncRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($validated['roles']);
        return $this->sendResponse(new UserResource($user->load('roles')), 'Roles synced successfully');
    }

    /**
     * Get all users with their roles for management
     */
    public function withRoles(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $users = User::with('roles')->paginate($perPage);
        return $this->sendResponse(UserResource::collection($users), 'Users with roles retrieved successfully');
    }

    /**
     * Bulk update user status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'status' => 'required|integer|in:0,1,2',
        ]);

        $updated = User::whereIn('id', $validated['user_ids'])
            ->update(['status' => $validated['status']]);

        return $this->sendResponse(
            ['updated_count' => $updated],
            "Successfully updated {$updated} users"
        );
    }

    /**
     * Bulk delete users
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $deleted = User::whereIn('id', $validated['user_ids'])->delete();

        return $this->sendResponse(
            ['deleted_count' => $deleted],
            "Successfully deleted {$deleted} users"
        );
    }

    /**
     * Get user profile with detailed information
     */
    public function profile(User $user)
    {
        $user->load([
            'roles.permissions',
            'userable',
            'auditTrails' => function ($query) {
                $query->latest()->limit(10);
            }
        ]);

        return $this->sendResponse(new UserResource($user), 'User profile retrieved successfully');
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'current_password' => 'required_if:self,true',
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'required|same:password',
            'self' => 'boolean',
        ]);

        // If user is updating their own password, verify current password
        if ($validated['self'] ?? false) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->sendError('Current password is incorrect', [], 422);
            }
        }

        $user->update(['password' => bcrypt($validated['password'])]);

        return $this->sendResponse([], 'Password updated successfully');
    }

    /**
     * Toggle user status (activate/deactivate)
     */
    public function toggleStatus(User $user)
    {
        $newStatus = $user->status === 1 ? 0 : 1;
        $user->update(['status' => $newStatus]);

        $statusText = $newStatus === 1 ? 'activated' : 'deactivated';
        return $this->sendResponse(
            new UserResource($user),
            "User {$statusText} successfully"
        );
    }

    /**
     * Get user activities/audit trail
     */
    public function activities(Request $request, User $user)
    {
        $limit = $request->get('limit', 50);

        $activities = $user->auditTrails()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'type' => $audit->event,
                    'description' => $this->getActivityDescription($audit),
                    'properties' => $audit->new_values,
                    'created_at' => $audit->created_at,
                ];
            });

        return $this->sendResponse($activities, 'User activities retrieved successfully');
    }

    /**
     * Update user roles
     */
    public function updateRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($validated['role_ids']);
        $user->load('roles');

        return $this->sendResponse(
            new UserResource($user),
            'User roles updated successfully'
        );
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request, User $user)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');

            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->update(['avatar' => $path]);
        }

        return $this->sendResponse(
            new UserResource($user),
            'Avatar uploaded successfully'
        );
    }

    /**
     * Toggle two-factor authentication
     */
    public function toggle2FA(User $user)
    {
        $newStatus = !$user->two_factor_enabled;
        $user->update(['two_factor_enabled' => $newStatus]);

        $statusText = $newStatus ? 'enabled' : 'disabled';
        return $this->sendResponse(
            new UserResource($user),
            "Two-factor authentication {$statusText} successfully"
        );
    }

    /**
     * Revoke all user sessions
     */
    public function revokeAllSessions(User $user)
    {
        // Delete all tokens for this user
        $user->tokens()->delete();

        return $this->sendResponse(
            [],
            'All sessions revoked successfully'
        );
    }

    /**
     * Get activity description based on audit event
     */
    private function getActivityDescription($audit)
    {
        switch ($audit->event) {
            case 'created':
                return 'User account created';
            case 'updated':
                return 'Profile information updated';
            case 'deleted':
                return 'User account deleted';
            case 'login':
                return 'User logged in';
            case 'logout':
                return 'User logged out';
            case 'password_changed':
                return 'Password changed';
            case 'role_assigned':
                return 'Role assigned to user';
            case 'role_removed':
                return 'Role removed from user';
            default:
                return 'User activity recorded';
        }
    }

    /**
     * Start impersonating a user
     */
    public function impersonate(User $user)
    {
        $currentUser = Auth::user();

        // Check if current user has permission to impersonate
        if (!$currentUser->hasRole('Super Admin') && !$currentUser->can('impersonate_users')) {
            return $this->sendError('Unauthorized to impersonate users', [], 403);
        }

        // Cannot impersonate yourself
        if ($currentUser->id === $user->id) {
            return $this->sendError('Cannot impersonate yourself', [], 400);
        }

        // Cannot impersonate another admin (unless you're super admin)
        if ($user->hasRole('Super Admin') && !$currentUser->hasRole('Super Admin')) {
            return $this->sendError('Cannot impersonate super admin', [], 403);
        }

        // Store original user ID in session
        Session::put('impersonated_by', $currentUser->id);

        // Login as the target user
        Auth::login($user);

        return $this->sendResponse([
            'user' => new UserResource($user),
            'original_user' => new UserResource($currentUser),
            'impersonation_token' => Session::getId()
        ], 'Impersonation started successfully');
    }

    /**
     * Stop impersonating and return to original user
     */
    public function stopImpersonation()
    {
        if (!Session::has('impersonated_by')) {
            return $this->sendError('Not currently impersonating', [], 400);
        }

        $originalUserId = Session::get('impersonated_by');
        $originalUser = User::find($originalUserId);

        if (!$originalUser) {
            return $this->sendError('Original user not found', [], 404);
        }

        // Clear impersonation session
        Session::forget('impersonated_by');

        // Login back as original user
        Auth::login($originalUser);

        return $this->sendResponse(
            new UserResource($originalUser),
            'Impersonation stopped successfully'
        );
    }

    /**
     * Export users to CSV
     */
    public function export(Request $request)
    {
        $filters = $request->only(['name', 'email', 'status', 'search']);

        $users = $this->userService->getForExport($filters);

        $csvData = [];
        $csvData[] = ['ID', 'Name', 'Username', 'Email', 'Phone', 'Status', 'Roles', 'Created At'];

        foreach ($users as $user) {
            $csvData[] = [
                $user->id,
                $user->name,
                $user->username,
                $user->email,
                $user->phone ?? '',
                $this->getStatusLabel($user->status),
                $user->roles->pluck('name')->join(', '),
                $user->created_at->format('Y-m-d H:i:s')
            ];
        }

        $filename = 'users_export_' . now()->format('Y_m_d_H_i_s') . '.csv';

        return $this->sendResponse([
            'filename' => $filename,
            'data' => $csvData,
            'count' => count($csvData) - 1 // Exclude header
        ], 'Users exported successfully');
    }

    /**
     * Import users from CSV
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_shift($csvData);

        $imported = 0;
        $errors = [];

        foreach ($csvData as $index => $row) {
            try {
                $userData = array_combine($header, $row);

                // Validate required fields
                if (empty($userData['name']) || empty($userData['email'])) {
                    $errors[] = "Row " . ($index + 2) . ": Name and email are required";
                    continue;
                }

                // Check if user already exists
                if (User::where('email', $userData['email'])->exists()) {
                    $errors[] = "Row " . ($index + 2) . ": User with email {$userData['email']} already exists";
                    continue;
                }

                // Create user
                $user = User::create([
                    'name' => $userData['name'],
                    'username' => $userData['username'] ?? strtolower(str_replace(' ', '.', $userData['name'])),
                    'email' => $userData['email'],
                    'phone' => $userData['phone'] ?? null,
                    'password' => Hash::make($userData['password'] ?? 'password123'),
                    'status' => $this->parseStatus($userData['status'] ?? 'Active'),
                ]);

                // Assign roles if specified
                if (!empty($userData['roles'])) {
                    $roleNames = array_map('trim', explode(',', $userData['roles']));
                    $roles = \App\Models\Role::whereIn('name', $roleNames)->get();
                    $user->roles()->sync($roles->pluck('id'));
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return $this->sendResponse([
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($csvData)
        ], "Import completed. {$imported} users imported successfully");
    }

    /**
     * Get user activity statistics
     */
    public function activityStats(User $user)
    {
        $stats = [
            'total_logins' => $user->auditTrails()->where('event', 'login')->count(),
            'last_login' => $user->last_login_at,
            'total_activities' => $user->auditTrails()->count(),
            'profile_updates' => $user->auditTrails()->where('event', 'updated')->count(),
            'password_changes' => $user->auditTrails()->where('event', 'password_changed')->count(),
        ];

        return $this->sendResponse($stats, 'User activity statistics retrieved successfully');
    }

    /**
     * Parse status from string to integer
     */
    private function parseStatus($status)
    {
        switch (strtolower($status)) {
            case 'active':
                return 1;
            case 'inactive':
                return 0;
            case 'suspended':
                return 2;
            default:
                return 1;
        }
    }

    /**
     * Get status label from integer
     */
    private function getStatusLabel($status)
    {
        switch ($status) {
            case 1:
                return 'Active';
            case 0:
                return 'Inactive';
            case 2:
                return 'Suspended';
            default:
                return 'Unknown';
        }
    }
}