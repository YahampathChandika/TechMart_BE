<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingCart extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'product_id',
        'quantity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Relationship: Cart item belongs to customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship: Cart item belongs to product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get total price for this cart item
     */
    public function getTotalPriceAttribute()
    {
        return $this->quantity * $this->product->sell_price;
    }

    /**
     * Scope: Get cart items for specific customer
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Add product to cart or update quantity if exists
     */
    public static function addToCart($customerId, $productId, $quantity = 1)
    {
        $cartItem = self::where('customer_id', $customerId)
                       ->where('product_id', $productId)
                       ->first();

        if ($cartItem) {
            // Update existing cart item
            $cartItem->increment('quantity', $quantity);
            return $cartItem;
        } else {
            // Create new cart item
            return self::create([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateQuantity($newQuantity)
    {
        if ($newQuantity <= 0) {
            $this->delete();
            return null;
        }
        
        $this->update(['quantity' => $newQuantity]);
        return $this;
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart()
    {
        return $this->delete();
    }

    /**
     * Clear all cart items for customer
     */
    public static function clearCartForCustomer($customerId)
    {
        return self::where('customer_id', $customerId)->delete();
    }

    /**
     * Get cart summary for customer
     */
    public static function getCartSummary($customerId)
    {
        $cartItems = self::with('product')
                        ->where('customer_id', $customerId)
                        ->get();

        $totalItems = $cartItems->sum('quantity');
        $totalAmount = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->sell_price;
        });

        return [
            'items' => $cartItems,
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
        ];
    }
}