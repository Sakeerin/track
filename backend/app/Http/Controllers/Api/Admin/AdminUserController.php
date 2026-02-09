<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    /**
     * List all users
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:name,email,created_at,last_login_at',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        try {
            $query = User::with('roles');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('role')) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->input('per_page', 20);
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => collect($users->items())->map(fn($u) => $this->formatUser($u)),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                    ],
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list users', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve users',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Get a single user
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with('roles')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatUser($user),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user', ['user_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve user',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_active' => $request->boolean('is_active', true),
                'email_verified_at' => now(),
            ]);

            $user->assignRole($request->role);

            AuditLog::log(
                AuditLog::ACTION_CREATE,
                $request->user(),
                User::class,
                $user->id,
                null,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $request->role,
                ]
            );

            Log::info('User created', [
                'created_by' => $request->user()->id,
                'new_user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatUser($user->load('roles')),
                'message' => 'User created successfully',
                'timestamp' => now()->toISOString(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create user', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create user',
                'error_code' => 'CREATE_ERROR',
            ], 500);
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($id)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            $oldValues = $user->only(['name', 'email', 'is_active']);

            $updateData = array_filter($request->only(['name', 'email', 'is_active']), fn($v) => $v !== null);

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            if (empty($updateData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No fields to update',
                    'error_code' => 'NO_CHANGES',
                ], 400);
            }

            $user->update($updateData);

            AuditLog::log(
                AuditLog::ACTION_UPDATE,
                $request->user(),
                User::class,
                $user->id,
                $oldValues,
                array_diff_key($updateData, ['password' => true]) // Don't log password
            );

            Log::info('User updated', [
                'updated_by' => $request->user()->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatUser($user->load('roles')),
                'message' => 'User updated successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update user', ['user_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update user',
                'error_code' => 'UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Update user roles
     */
    public function updateRoles(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
        ]);

        try {
            $user = User::with('roles')->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            // Prevent user from modifying their own roles
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot modify your own roles',
                    'error_code' => 'SELF_MODIFICATION',
                ], 403);
            }

            $oldRoles = $user->getRoleNames()->toArray();
            $user->syncRoles($request->roles);

            AuditLog::log(
                AuditLog::ACTION_ROLE_CHANGED,
                $request->user(),
                User::class,
                $user->id,
                ['roles' => $oldRoles],
                ['roles' => $request->roles]
            );

            Log::info('User roles updated', [
                'updated_by' => $request->user()->id,
                'user_id' => $user->id,
                'old_roles' => $oldRoles,
                'new_roles' => $request->roles,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatUser($user->fresh('roles')),
                'message' => 'Roles updated successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update user roles', ['user_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update roles',
                'error_code' => 'UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Activate or deactivate a user
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            // Prevent user from deactivating themselves
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot deactivate your own account',
                    'error_code' => 'SELF_MODIFICATION',
                ], 403);
            }

            $oldValue = $user->is_active;
            $user->update(['is_active' => !$user->is_active]);

            // Revoke all tokens if deactivating
            if (!$user->is_active) {
                $user->tokens()->delete();
            }

            AuditLog::log(
                AuditLog::ACTION_UPDATE,
                $request->user(),
                User::class,
                $user->id,
                ['is_active' => $oldValue],
                ['is_active' => $user->is_active]
            );

            $action = $user->is_active ? 'activated' : 'deactivated';
            Log::info("User {$action}", [
                'updated_by' => $request->user()->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatUser($user->load('roles')),
                'message' => "User {$action} successfully",
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle user active status', ['user_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update user',
                'error_code' => 'UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'error_code' => 'NOT_FOUND',
                ], 404);
            }

            // Prevent user from deleting themselves
            if ($user->id === $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete your own account',
                    'error_code' => 'SELF_MODIFICATION',
                ], 403);
            }

            $userData = $user->only(['id', 'name', 'email']);
            $user->tokens()->delete();
            $user->delete();

            AuditLog::log(
                AuditLog::ACTION_DELETE,
                $request->user(),
                User::class,
                $id,
                $userData,
                null
            );

            Log::info('User deleted', [
                'deleted_by' => $request->user()->id,
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete user', ['user_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete user',
                'error_code' => 'DELETE_ERROR',
            ], 500);
        }
    }

    /**
     * Get available roles
     */
    public function roles(): JsonResponse
    {
        try {
            $roles = Role::all()->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]);

            return response()->json([
                'success' => true,
                'data' => $roles,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get roles', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve roles',
                'error_code' => 'RETRIEVAL_ERROR',
            ], 500);
        }
    }

    /**
     * Format user data for response
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'provider' => $user->provider,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames(),
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
