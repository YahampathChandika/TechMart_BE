<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ShoppingCart;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getStats()
    {
        $user = auth('api')->user();
        
        // Basic stats accessible to all authenticated users
        $stats = [
            'products' => [
                'total' => Product::count(),
                'active' => Product::active()->count(),
                'inactive' => Product::where('is_active', false)->count(),
                'out_of_stock' => Product::where('quantity', 0)->count(),
                'low_stock' => Product::where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
            ],
            'customers' => [
                'total' => Customer::count(),
                'active' => Customer::where('is_active', true)->count(),
                'with_cart_items' => Customer::whereHas('cartItems')->count(),
            ]
        ];

        // Admin-only stats
        if ($user->isAdmin()) {
            $stats['users'] = [
                'total' => User::count(),
                'admin_users' => User::where('role', 'admin')->count(),
                'regular_users' => User::where('role', 'user')->count(),
                'active_users' => User::where('is_active', true)->count(),
                'users_with_privileges' => User::whereHas('privileges', function ($q) {
                    $q->where(function ($query) {
                        $query->where('can_add_products', true)
                              ->orWhere('can_update_products', true)
                              ->orWhere('can_delete_products', true);
                    });
                })->count(),
            ];

            $stats['sales'] = [
                'total_cart_value' => ShoppingCart::with('product')->get()->sum(function ($item) {
                    return $item->quantity * $item->product->sell_price;
                }),
                'total_cart_items' => ShoppingCart::sum('quantity'),
                'unique_customers_with_cart' => ShoppingCart::distinct('customer_id')->count(),
            ];
        }

        // User-specific stats (if regular user)
        if ($user->isUser()) {
            $stats['my_products'] = [
                'total' => $user->products()->count(),
                'active' => $user->products()->active()->count(),
                'inactive' => $user->products()->where('is_active', false)->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get recent activities (optional enhancement)
     */
    public function getRecentActivities()
    {
        $user = auth('api')->user();
        $activities = [];

        // Recent products
        $recentProducts = Product::latest()->limit(5)->get(['id', 'name', 'brand', 'created_at']);
        
        // Recent customers
        if ($user->isAdmin()) {
            $recentCustomers = Customer::latest()->limit(5)->get(['id', 'first_name', 'last_name', 'email', 'created_at']);
            $activities['recent_customers'] = $recentCustomers;
        }

        $activities['recent_products'] = $recentProducts;

        return response()->json([
            'success' => true,
            'message' => 'Recent activities retrieved successfully',
            'data' => $activities
        ]);
    }
}