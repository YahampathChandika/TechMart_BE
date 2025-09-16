<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPrivilege;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get all users (admin only)
     */
    public function index(Request $request)
    {
        $query = User::with('privileges');

        // Search filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'LIKE', "%{$request->search}%")
                  ->orWhere('last_name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['first_name', 'last_name', 'email', 'role', 'is_active', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    /**
     * Get single user details
     */
    public function show($id)
    {
        $user = User::with('privileges', 'products')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Add user statistics
        $user->products_count = $user->products()->count();
        $user->active_products_count = $user->products()->active()->count();

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    /**
     * Create new user (admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'contact' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,user',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'contact' => $request->contact,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => $request->get('is_active', true),
        ]);

        // Create default privileges for regular users
        if ($user->role === 'user') {
            UserPrivilege::createDefaultForUser($user->id);
        }

        $user->load('privileges');

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent admin from deactivating themselves
        $currentUser = auth('api')->user();
        if ($user->id === $currentUser->id && $request->has('is_active') && !$request->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|between:2,100',
            'last_name' => 'sometimes|required|string|between:2,100',
            'email' => 'sometimes|required|string|email|max:100|unique:users,email,' . $user->id,
            'contact' => 'sometimes|required|string|max:20',
            'password' => 'sometimes|required|string|min:6',
            'role' => 'sometimes|required|in:admin,user',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $data = $request->only(['first_name', 'last_name', 'email', 'contact', 'role', 'is_active']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // If role is changing from user to admin, remove privileges
        if ($request->filled('role') && $user->role === 'user' && $request->role === 'admin') {
            if ($user->privileges !== null) {
                $user->privileges->delete();
            }
        }

        // If role is changing from admin to user, create default privileges
        if ($request->filled('role') && $user->role === 'admin' && $request->role === 'user') {
            UserPrivilege::createDefaultForUser($user->id);
        }

        $user->update($data);
        $user->load('privileges');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent admin from deleting themselves
        $currentUser = auth('api')->user();
        if ($user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        // Check if user has created products
        $productsCount = $user->products()->count();
        if ($productsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete user. User has created {$productsCount} products. Please reassign or delete products first."
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Toggle user status (activate/deactivate)
     */
    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent admin from deactivating themselves
        $currentUser = auth('api')->user();
        if ($user->id === $currentUser->id && $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account'
            ], 400);
        }

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "User {$status} successfully",
            'data' => $user
        ]);
    }

    /**
     * Get user statistics (for admin dashboard)
     */
    public function statistics()
    {
        $stats = [
            'total_users' => User::count(),
            'admin_users' => User::where('role', 'admin')->count(),
            'regular_users' => User::where('role', 'user')->count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'users_with_products' => User::whereHas('products')->count(),
            'users_with_privileges' => User::whereHas('privileges', function ($q) {
                $q->where(function ($query) {
                    $query->where('can_add_products', true)
                          ->orWhere('can_update_products', true)
                          ->orWhere('can_delete_products', true);
                });
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'User statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get user privileges
     */
    public function getPrivileges($id)
    {
        $user = User::with('privileges')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => true,
                'message' => 'Admin users have all privileges',
                'data' => [
                    'user' => $user,
                    'privileges' => [
                        'can_add_products' => true,
                        'can_update_products' => true,
                        'can_delete_products' => true,
                    ],
                    'is_admin' => true
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User privileges retrieved successfully',
            'data' => [
                'user' => $user,
                'privileges' => $user->privileges ?? [
                    'can_add_products' => false,
                    'can_update_products' => false,
                    'can_delete_products' => false,
                ],
                'is_admin' => false
            ]
        ]);
    }

    /**
     * Update user privileges
     */
    public function updatePrivileges(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify admin privileges'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'can_add_products' => 'boolean',
            'can_update_products' => 'boolean',
            'can_delete_products' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Get or create user privileges
        $privileges = $user->privileges ?? UserPrivilege::createDefaultForUser($user->id);

        $privilegeData = [];
        if ($request->has('can_add_products')) {
            $privilegeData['can_add_products'] = $request->can_add_products;
        }
        if ($request->has('can_update_products')) {
            $privilegeData['can_update_products'] = $request->can_update_products;
        }
        if ($request->has('can_delete_products')) {
            $privilegeData['can_delete_products'] = $request->can_delete_products;
        }

        $privileges->update($privilegeData);
        $user->load('privileges');

        return response()->json([
            'success' => true,
            'message' => 'User privileges updated successfully',
            'data' => $user
        ]);
    }
}