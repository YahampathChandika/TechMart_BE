<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShoppingCart;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get customer's cart items
     */
    public function index()
    {
        $customer = auth('customer')->user();
        $cartSummary = ShoppingCart::getCartSummary($customer->id);

        return response()->json([
            'success' => true,
            'message' => 'Cart items retrieved successfully',
            'data' => [
                'customer_id' => $customer->id,
                'items' => $cartSummary['items'],
                'summary' => [
                    'total_items' => $cartSummary['total_items'],
                    'total_amount' => $cartSummary['total_amount'],
                    'currency' => 'USD'
                ]
            ]
        ]);
    }

    /**
     * Add product to cart
     */
    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $customer = auth('customer')->user();
        $product = Product::find($request->product_id);

        // Check if product is active and available
        if (!$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available'
            ], 400);
        }

        // Check stock availability
        $existingCartItem = ShoppingCart::where('customer_id', $customer->id)
                                       ->where('product_id', $product->id)
                                       ->first();

        $currentCartQuantity = $existingCartItem ? $existingCartItem->quantity : 0;
        $requestedTotalQuantity = $currentCartQuantity + $request->quantity;

        if ($requestedTotalQuantity > $product->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient stock. Available: {$product->quantity}, Already in cart: {$currentCartQuantity}"
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Add or update cart item
            $cartItem = ShoppingCart::addToCart($customer->id, $product->id, $request->quantity);
            
            // Load product relationship
            $cartItem->load('product');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'data' => [
                    'cart_item' => $cartItem,
                    'total_quantity' => $cartItem->quantity,
                    'item_total' => $cartItem->total_price
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart'
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $customer = auth('customer')->user();
        
        $cartItem = ShoppingCart::with('product')
                               ->where('customer_id', $customer->id)
                               ->where('id', $itemId)
                               ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        // Check stock availability
        if ($request->quantity > $cartItem->product->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient stock. Available: {$cartItem->product->quantity}"
            ], 400);
        }

        // Check if product is still active
        if (!$cartItem->product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Product is no longer available'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $updatedItem = $cartItem->updateQuantity($request->quantity);
            
            if (!$updatedItem) {
                // Item was removed (quantity was 0 or negative)
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Cart item removed successfully',
                    'data' => [
                        'removed' => true,
                        'item_id' => $itemId
                    ]
                ]);
            }

            $updatedItem->load('product');
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => [
                    'cart_item' => $updatedItem,
                    'item_total' => $updatedItem->total_price
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart item'
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem($itemId)
    {
        $customer = auth('customer')->user();
        
        $cartItem = ShoppingCart::where('customer_id', $customer->id)
                               ->where('id', $itemId)
                               ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        try {
            $productName = $cartItem->product->name;
            $cartItem->removeFromCart();

            return response()->json([
                'success' => true,
                'message' => "'{$productName}' removed from cart successfully",
                'data' => [
                    'removed_item_id' => $itemId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove cart item'
            ], 500);
        }
    }

    /**
     * Clear entire cart
     */
    public function clearCart()
    {
        $customer = auth('customer')->user();
        
        try {
            $itemsRemoved = ShoppingCart::clearCartForCustomer($customer->id);

            return response()->json([
                'success' => true,
                'message' => "Cart cleared successfully. {$itemsRemoved} items removed.",
                'data' => [
                    'items_removed' => $itemsRemoved,
                    'customer_id' => $customer->id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart'
            ], 500);
        }
    }

    /**
     * Get cart summary (count and total)
     */
    public function summary()
    {
        $customer = auth('customer')->user();
        $cartSummary = ShoppingCart::getCartSummary($customer->id);

        return response()->json([
            'success' => true,
            'message' => 'Cart summary retrieved successfully',
            'data' => [
                'customer_id' => $customer->id,
                'total_items' => $cartSummary['total_items'],
                'total_amount' => $cartSummary['total_amount'],
                'currency' => 'USD',
                'is_empty' => $cartSummary['total_items'] === 0
            ]
        ]);
    }

    /**
     * Validate cart items (check availability and stock)
     */
    public function validateCart()
    {
        $customer = auth('customer')->user();
        $cartItems = ShoppingCart::with('product')
                                ->where('customer_id', $customer->id)
                                ->get();

        $issues = [];
        $validItems = [];

        foreach ($cartItems as $item) {
            $issue = null;

            // Check if product still exists and is active
            if (!$item->product || !$item->product->is_active) {
                $issue = [
                    'type' => 'unavailable',
                    'message' => 'Product is no longer available'
                ];
            }
            // Check stock availability
            else if ($item->quantity > $item->product->quantity) {
                $issue = [
                    'type' => 'insufficient_stock',
                    'message' => "Insufficient stock. Available: {$item->product->quantity}, In cart: {$item->quantity}",
                    'available_quantity' => $item->product->quantity
                ];
            }

            if ($issue) {
                $issues[] = [
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown Product',
                    'quantity_in_cart' => $item->quantity,
                    'issue' => $issue
                ];
            } else {
                $validItems[] = $item;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart validation completed',
            'data' => [
                'is_valid' => empty($issues),
                'total_items' => $cartItems->count(),
                'valid_items' => count($validItems),
                'issues_found' => count($issues),
                'issues' => $issues,
                'valid_items_total' => collect($validItems)->sum(function ($item) {
                    return $item->quantity * $item->product->sell_price;
                })
            ]
        ]);
    }

    /**
     * Quick add product to cart (single quantity)
     */
    public function quickAdd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Use the regular addToCart method with quantity = 1
        $request->merge(['quantity' => 1]);
        return $this->addToCart($request);
    }

    /**
     * Update multiple cart items at once
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.cart_item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $customer = auth('customer')->user();
        $results = [];
        $errors = [];

        try {
            DB::beginTransaction();

            foreach ($request->items as $itemUpdate) {
                $cartItem = ShoppingCart::with('product')
                                       ->where('customer_id', $customer->id)
                                       ->where('id', $itemUpdate['cart_item_id'])
                                       ->first();

                if (!$cartItem) {
                    $errors[] = [
                        'cart_item_id' => $itemUpdate['cart_item_id'],
                        'error' => 'Cart item not found'
                    ];
                    continue;
                }

                // Check stock if increasing quantity
                if ($itemUpdate['quantity'] > 0 && $itemUpdate['quantity'] > $cartItem->product->quantity) {
                    $errors[] = [
                        'cart_item_id' => $itemUpdate['cart_item_id'],
                        'error' => "Insufficient stock. Available: {$cartItem->product->quantity}"
                    ];
                    continue;
                }

                $updatedItem = $cartItem->updateQuantity($itemUpdate['quantity']);
                
                if (!$updatedItem) {
                    $results[] = [
                        'cart_item_id' => $itemUpdate['cart_item_id'],
                        'action' => 'removed',
                        'product_name' => $cartItem->product->name
                    ];
                } else {
                    $results[] = [
                        'cart_item_id' => $itemUpdate['cart_item_id'],
                        'action' => 'updated',
                        'new_quantity' => $updatedItem->quantity,
                        'item_total' => $updatedItem->total_price
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk cart update completed',
                'data' => [
                    'processed' => count($results),
                    'errors' => count($errors),
                    'results' => $results,
                    'errors_details' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart items'
            ], 500);
        }
    }

    /**
     * Get cart items count (for navbar badge)
     */
    public function count()
    {
        $customer = auth('customer')->user();
        $totalItems = ShoppingCart::where('customer_id', $customer->id)->sum('quantity');

        return response()->json([
            'success' => true,
            'message' => 'Cart count retrieved successfully',
            'data' => [
                'count' => $totalItems,
                'customer_id' => $customer->id
            ]
        ]);
    }

    /**
     * Check if specific product is in cart
     */
    public function checkProduct($productId)
    {
        $customer = auth('customer')->user();
        $cartItem = ShoppingCart::with('product')
                               ->where('customer_id', $customer->id)
                               ->where('product_id', $productId)
                               ->first();

        return response()->json([
            'success' => true,
            'message' => 'Product cart status retrieved',
            'data' => [
                'in_cart' => !!$cartItem,
                'quantity' => $cartItem ? $cartItem->quantity : 0,
                'cart_item_id' => $cartItem ? $cartItem->id : null,
                'product_id' => $productId
            ]
        ]);
    }
}