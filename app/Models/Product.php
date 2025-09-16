<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'brand',
        'name',
        'image_path',
        'quantity',
        'cost_price',
        'sell_price',
        'description',
        'rating',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'rating' => 'integer',
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Product belongs to user who created it
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Product has many cart items
     */
    public function cartItems()
    {
        return $this->hasMany(ShoppingCart::class);
    }

    /**
     * Scope: Only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only in-stock products
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope: Filter by brand
     */
    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    /**
     * Scope: Filter by rating
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', '>=', $rating);
    }

    /**
     * Scope: Search by name or description
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%")
              ->orWhere('brand', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Scope: Filter by price range
     */
    public function scopePriceRange($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('sell_price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('sell_price', '<=', $max);
        }
        return $query;
    }

    /**
     * Get product profit margin
     */
    public function getProfitMarginAttribute()
    {
        if ($this->cost_price == 0) return 0;
        return (($this->sell_price - $this->cost_price) / $this->cost_price) * 100;
    }

    /**
     * Check if product is in stock
     */
    public function isInStock()
    {
        return $this->quantity > 0;
    }

    /**
     * Check if product is available (active and in stock)
     */
    public function isAvailable()
    {
        return $this->is_active && $this->isInStock();
    }

    /**
     * Get full image URL
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }
        
        // If it's already a full URL, return as is
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        
        // Otherwise, prepend storage URL
        return url('storage/' . $this->image_path);
    }

    /**
     * Get total quantity in all carts
     */
    public function getTotalInCartsAttribute()
    {
        return $this->cartItems()->sum('quantity');
    }

    /**
     * Get number of customers who have this product in cart
     */
    public function getCustomersWithProductAttribute()
    {
        return $this->cartItems()->distinct('customer_id')->count();
    }

    /**
     * Check if customer has this product in cart
     */
    public function isInCustomerCart($customerId)
    {
        return $this->cartItems()->where('customer_id', $customerId)->exists();
    }

    /**
     * Get quantity in specific customer's cart
     */
    public function getQuantityInCart($customerId)
    {
        $item = $this->cartItems()->where('customer_id', $customerId)->first();
        return $item ? $item->quantity : 0;
    }

    /**
     * Check if product can be added to cart (stock + availability)
     */
    public function canAddToCart($requestedQuantity = 1, $customerId = null)
    {
        if (!$this->is_active) {
            return [
                'can_add' => false,
                'reason' => 'Product is not available'
            ];
        }

        if (!$this->isInStock()) {
            return [
                'can_add' => false,
                'reason' => 'Product is out of stock'
            ];
        }

        $availableQuantity = $this->quantity;
        
        // If customer ID provided, account for existing cart quantity
        if ($customerId) {
            $existingInCart = $this->getQuantityInCart($customerId);
            $totalRequested = $existingInCart + $requestedQuantity;
            
            if ($totalRequested > $availableQuantity) {
                return [
                    'can_add' => false,
                    'reason' => "Insufficient stock. Available: {$availableQuantity}, Already in cart: {$existingInCart}",
                    'available' => $availableQuantity,
                    'in_cart' => $existingInCart,
                    'max_additional' => max(0, $availableQuantity - $existingInCart)
                ];
            }
        } else {
            if ($requestedQuantity > $availableQuantity) {
                return [
                    'can_add' => false,
                    'reason' => "Insufficient stock. Available: {$availableQuantity}",
                    'available' => $availableQuantity
                ];
            }
        }

        return [
            'can_add' => true,
            'available' => $availableQuantity
        ];
    }

    /**
     * Reduce stock quantity
     */
    public function reduceStock($quantity)
    {
        if ($this->quantity >= $quantity) {
            $this->decrement('quantity', $quantity);
            return true;
        }
        return false;
    }

    /**
     * Increase stock quantity
     */
    public function increaseStock($quantity)
    {
        $this->increment('quantity', $quantity);
    }
}