<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;

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

        $response = UserResource::collection($users);
        $response->additional([
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
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
        $user = $this->userService->create($data);
        return $this->sendResponse(new UserResource($user), 'User created successfully', 201);
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
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'status' => 'sometimes|in:active,inactive',
        ]);

        $user->update($validated);
        return $this->sendResponse(new UserResource($user), 'User updated successfully');
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
        $users = User::with('roles')->paginate(15);
        return $this->sendResponse(UserResource::collection($users), 'Users with roles retrieved successfully');
    }
}