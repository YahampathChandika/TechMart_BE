<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ShoppingCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Get all customers (admin/users can access)
     */
    public function index(Request $request)
    {
        $query = Customer::withCount('cartItems');

        // Search filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'LIKE', "%{$request->search}%")
                  ->orWhere('last_name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%")
                  ->orWhere('contact', 'LIKE', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('has_cart')) {
            if ($request->has_cart === 'true') {
                $query->has('cartItems');
            } else {
                $query->doesntHave('cartItems');
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['first_name', 'last_name', 'email', 'is_active', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ]
        ]);
    }

    /**
     * Get single customer details
     */
    public function show($id)
    {
        $customer = Customer::withCount('cartItems')->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        // Get cart summary
        $cartSummary = ShoppingCart::getCartSummary($customer->id);
        $customer->cart_summary = $cartSummary;

        return response()->json([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data' => $customer
        ]);
    }

    /**
     * Create new customer (admin/users only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:customers',
            'contact' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $customer = Customer::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'contact' => $request->contact,
            'password' => Hash::make($request->password),
            'is_active' => $request->get('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    /**
     * Update customer
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|between:2,100',
            'last_name' => 'sometimes|required|string|between:2,100',
            'email' => 'sometimes|required|string|email|max:100|unique:customers,email,' . $customer->id,
            'contact' => 'sometimes|required|string|max:20',
            'password' => 'sometimes|required|string|min:6',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $data = $request->only(['first_name', 'last_name', 'email', 'contact', 'is_active']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $customer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * Delete customer
     */
    public function destroy($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        // Clear customer's cart before deletion
        ShoppingCart::clearCartForCustomer($customer->id);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }

    /**
     * Toggle customer status (activate/deactivate)
     */
    public function toggleStatus($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $customer->update(['is_active' => !$customer->is_active]);

        $status = $customer->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Customer {$status} successfully",
            'data' => $customer
        ]);
    }

    /**
     * Get customer statistics (for admin dashboard)
     */
    public function statistics()
    {
        $stats = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('is_active', true)->count(),
            'inactive_customers' => Customer::where('is_active', false)->count(),
            'customers_with_cart' => Customer::whereHas('cartItems')->count(),
            'customers_without_cart' => Customer::whereDoesntHave('cartItems')->count(),
            'total_cart_items' => ShoppingCart::sum('quantity'),
            'average_cart_items' => round(
                Customer::whereHas('cartItems')
                    ->withCount('cartItems')
                    ->avg('cart_items_count') ?? 0, 
                2
            ),
        ];

        // Recent customers (last 30 days)
        $stats['recent_customers'] = Customer::where('created_at', '>=', now()->subDays(30))->count();

        return response()->json([
            'success' => true,
            'message' => 'Customer statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get customer's cart details
     */
    public function getCart($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $cartSummary = ShoppingCart::getCartSummary($customer->id);

        return response()->json([
            'success' => true,
            'message' => 'Customer cart retrieved successfully',
            'data' => [
                'customer' => $customer,
                'cart' => $cartSummary
            ]
        ]);
    }

    /**
     * Clear customer's cart
     */
    public function clearCart($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $itemsRemoved = ShoppingCart::clearCartForCustomer($customer->id);

        return response()->json([
            'success' => true,
            'message' => "Cleared {$itemsRemoved} items from customer's cart",
            'data' => [
                'customer_id' => $customer->id,
                'items_removed' => $itemsRemoved
            ]
        ]);
    }

    /**
     * Get customers with most cart items (top spenders analysis)
     */
    public function topCustomers(Request $request)
    {
        $limit = min($request->get('limit', 10), 50);

        $customers = Customer::with('cartItems.product')
            ->whereHas('cartItems')
            ->get()
            ->map(function ($customer) {
                $cartSummary = ShoppingCart::getCartSummary($customer->id);
                $customer->cart_total_amount = $cartSummary['total_amount'];
                $customer->cart_total_items = $cartSummary['total_items'];
                return $customer;
            })
            ->sortByDesc('cart_total_amount')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Top customers retrieved successfully',
            'data' => $customers
        ]);
    }

    /**
     * Export customers data (basic info for CSV/Excel export)
     */
    public function export(Request $request)
    {
        $query = Customer::query();

        // Apply same filters as index
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'LIKE', "%{$request->search}%")
                  ->orWhere('last_name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $customers = $query->orderBy('created_at', 'desc')->get();

        // Format data for export
        $exportData = $customers->map(function ($customer) {
            return [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'contact' => $customer->contact,
                'status' => $customer->is_active ? 'Active' : 'Inactive',
                'registration_date' => $customer->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Customer export data retrieved successfully',
            'data' => $exportData
        ]);
    }
}