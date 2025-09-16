<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be appended to arrays.
     *
     * @var array
     */
    protected $appends = [
        'image_url',
        'profit_margin',
        'availability_status',
        'price_range_category',
        'total_in_carts',
        'customers_with_product',
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

    /*
    |--------------------------------------------------------------------------
    | Existing Scopes (Enhanced)
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only inactive products
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: Only in-stock products
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope: Filter by single brand (backward compatibility)
     */
    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    /**
     * Scope: Filter by minimum rating (backward compatibility)
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', '>=', $rating);
    }

    /**
     * Scope: Search by name, description, or brand (enhanced)
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
     * Scope: Filter by price range (enhanced)
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

    /*
    |--------------------------------------------------------------------------
    | New Enhanced Search Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Filter by multiple brands
     */
    public function scopeByBrands($query, array $brands)
    {
        return $query->whereIn('brand', $brands);
    }

    /**
     * Scope: Filter by rating range
     */
    public function scopeRatingRange($query, $minRating, $maxRating)
    {
        return $query->whereBetween('rating', [$minRating, $maxRating]);
    }

    /**
     * Scope: Products out of stock
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '=', 0);
    }

    /**
     * Scope: Low stock products
     */
    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('quantity', '>', 0)->where('quantity', '<=', $threshold);
    }

    /**
     * Scope: High stock products
     */
    public function scopeHighStock($query, $threshold = 50)
    {
        return $query->where('quantity', '>', $threshold);
    }

    /**
     * Scope: Filter by quantity range
     */
    public function scopeQuantityRange($query, $minQuantity = null, $maxQuantity = null)
    {
        if ($minQuantity !== null) {
            $query->where('quantity', '>=', $minQuantity);
        }
        if ($maxQuantity !== null) {
            $query->where('quantity', '<=', $maxQuantity);
        }
        return $query;
    }

    /**
     * Scope: Filter by creation date range
     */
    public function scopeCreatedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter by creator(s)
     */
    public function scopeByCreator($query, $creatorIds)
    {
        if (is_array($creatorIds)) {
            return $query->whereIn('created_by', $creatorIds);
        }
        return $query->where('created_by', $creatorIds);
    }

    /**
     * Scope: Filter by profit margin
     */
    public function scopeByProfitMargin($query, $minMargin, $maxMargin = null)
    {
        $query->whereRaw('((sell_price - cost_price) / cost_price * 100) >= ?', [$minMargin]);
        
        if ($maxMargin !== null) {
            $query->whereRaw('((sell_price - cost_price) / cost_price * 100) <= ?', [$maxMargin]);
        }
        
        return $query;
    }

    /**
     * Scope: Filter by cost price range (admin only)
     */
    public function scopeCostPriceRange($query, $minCost = null, $maxCost = null)
    {
        if ($minCost !== null) {
            $query->where('cost_price', '>=', $minCost);
        }
        if ($maxCost !== null) {
            $query->where('cost_price', '<=', $maxCost);
        }
        return $query;
    }

    /**
     * Scope: Popular products (high rating + recent)
     */
    public function scopePopular($query)
    {
        return $query->where('rating', '>=', 4)
                    ->where('created_at', '>=', now()->subMonths(3))
                    ->orderByRaw('(rating * 0.7 + (DATEDIFF(NOW(), created_at) * -0.01)) DESC');
    }

    /**
     * Scope: Featured products (high rating + good stock)
     */
    public function scopeFeatured($query)
    {
        return $query->where('rating', '>=', 4)
                    ->where('quantity', '>', 0)
                    ->where('is_active', true);
    }

    /**
     * Scope: New arrivals
     */
    public function scopeNewArrivals($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Best sellers (based on rating and stock movement)
     */
    public function scopeBestSellers($query)
    {
        // This would typically be based on actual sales data
        // For now, we'll use rating and low stock as indicators
        return $query->where('rating', '>=', 4)
                    ->where('quantity', '<', 20) // Assuming low stock means high sales
                    ->where('quantity', '>', 0)
                    ->orderBy('rating', 'desc');
    }

    /**
     * Scope: Filter by availability status
     */
    public function scopeByAvailability($query, $status)
    {
        switch ($status) {
            case 'in_stock':
                return $query->where('quantity', '>', 0);
            case 'out_of_stock':
                return $query->where('quantity', '=', 0);
            case 'low_stock':
                return $query->where('quantity', '>', 0)->where('quantity', '<=', 10);
            case 'high_stock':
                return $query->where('quantity', '>', 50);
            default:
                return $query;
        }
    }

    /**
     * Scope: Filter by price category
     */
    public function scopeByPriceCategory($query, $category)
    {
        switch ($category) {
            case 'budget':
                return $query->where('sell_price', '<', 100);
            case 'mid_range':
                return $query->whereBetween('sell_price', [100, 499.99]);
            case 'premium':
                return $query->whereBetween('sell_price', [500, 999.99]);
            case 'luxury':
                return $query->where('sell_price', '>=', 1000);
            default:
                return $query;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Accessors (Enhanced)
    |--------------------------------------------------------------------------
    */

    /**
     * Get product profit margin
     */
    public function getProfitMarginAttribute()
    {
        if ($this->cost_price == 0) return 0;
        return round((($this->sell_price - $this->cost_price) / $this->cost_price) * 100, 2);
    }

    /**
     * Get full image URL (enhanced)
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return asset('images/placeholder-product.jpg'); // Default placeholder
        }
        
        // If it's already a full URL, return as is
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        
        // Check if file exists in storage
        if (Storage::disk('public')->exists($this->image_path)) {
            return Storage::url($this->image_path);
        }
        
        // Return placeholder if file doesn't exist
        return asset('images/placeholder-product.jpg');
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

    /*
    |--------------------------------------------------------------------------
    | New Enhanced Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get availability status
     */
    public function getAvailabilityStatusAttribute()
    {
        if (!$this->is_active) {
            return 'inactive';
        } elseif ($this->quantity == 0) {
            return 'out_of_stock';
        } elseif ($this->quantity <= 10) {
            return 'low_stock';
        } elseif ($this->quantity > 50) {
            return 'high_stock';
        } else {
            return 'in_stock';
        }
    }

    /**
     * Get price range category
     */
    public function getPriceRangeCategoryAttribute()
    {
        if ($this->sell_price < 100) {
            return 'budget';
        } elseif ($this->sell_price < 500) {
            return 'mid_range';
        } elseif ($this->sell_price < 1000) {
            return 'premium';
        } else {
            return 'luxury';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Helper Methods (Enhanced)
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | New Static Methods for Enhanced Search
    |--------------------------------------------------------------------------
    */

    /**
     * Get all unique brands
     */
    public static function getUniqueBrands()
    {
        return self::active()
                  ->select('brand')
                  ->distinct()
                  ->orderBy('brand')
                  ->pluck('brand');
    }

    /**
     * Get price statistics
     */
    public static function getPriceStats()
    {
        return [
            'min' => self::active()->min('sell_price'),
            'max' => self::active()->max('sell_price'),
            'avg' => round(self::active()->avg('sell_price'), 2),
        ];
    }

    /**
     * Get rating distribution
     */
    public static function getRatingDistribution()
    {
        return self::active()
                  ->selectRaw('rating, COUNT(*) as count')
                  ->groupBy('rating')
                  ->orderBy('rating', 'desc')
                  ->get();
    }

    /**
     * Get stock statistics
     */
    public static function getStockStats()
    {
        return [
            'total_products' => self::active()->count(),
            'in_stock' => self::active()->inStock()->count(),
            'out_of_stock' => self::active()->outOfStock()->count(),
            'low_stock' => self::active()->lowStock()->count(),
            'high_stock' => self::active()->highStock()->count(),
        ];
    }

    /**
     * Search products with multiple criteria
     */
    public static function advancedSearch(array $criteria)
    {
        $query = self::active()->with('creator:id,first_name,last_name');

        // Apply each criteria if present
        if (!empty($criteria['search'])) {
            $query->search($criteria['search']);
        }

        if (!empty($criteria['brands'])) {
            $query->byBrands($criteria['brands']);
        }

        if (isset($criteria['min_price']) || isset($criteria['max_price'])) {
            $query->priceRange($criteria['min_price'] ?? null, $criteria['max_price'] ?? null);
        }

        if (!empty($criteria['min_rating'])) {
            $query->byRating($criteria['min_rating']);
        }

        if (!empty($criteria['availability'])) {
            $query->byAvailability($criteria['availability']);
        }

        if (!empty($criteria['price_category'])) {
            $query->byPriceCategory($criteria['price_category']);
        }

        if (!empty($criteria['created_from']) || !empty($criteria['created_to'])) {
            $query->createdBetween(
                $criteria['created_from'] ?? '1970-01-01',
                $criteria['created_to'] ?? now()
            );
        }

        if (!empty($criteria['min_profit_margin'])) {
            $query->byProfitMargin($criteria['min_profit_margin']);
        }

        if (!empty($criteria['created_by'])) {
            $query->byCreator($criteria['created_by']);
        }

        return $query;
    }

    /**
     * Get products by popularity
     */
    public static function getPopularProducts($limit = 10)
    {
        return self::popular()->take($limit)->get();
    }

    /**
     * Get featured products
     */
    public static function getFeaturedProducts($limit = 8)
    {
        return self::featured()->take($limit)->get();
    }

    /**
     * Get new arrivals
     */
    public static function getNewArrivals($days = 30, $limit = 12)
    {
        return self::newArrivals($days)->take($limit)->get();
    }

    /**
     * Get best sellers
     */
    public static function getBestSellers($limit = 6)
    {
        return self::bestSellers()->take($limit)->get();
    }

    /**
     * Get products on sale (where sell_price is significantly higher than cost_price)
     */
    public static function getOnSaleProducts($minProfitMargin = 30, $limit = 10)
    {
        return self::active()
                  ->byProfitMargin($minProfitMargin)
                  ->take($limit)
                  ->get();
    }

    /**
     * Get low stock products (for admin alerts)
     */
    public static function getLowStockProducts($threshold = 10)
    {
        return self::active()->lowStock($threshold)->get();
    }

    /**
     * Get products by brand with statistics
     */
    public static function getProductsByBrandWithStats($brand)
    {
        $products = self::active()->byBrand($brand)->get();
        
        return [
            'brand' => $brand,
            'products' => $products,
            'total_count' => $products->count(),
            'avg_price' => $products->avg('sell_price'),
            'avg_rating' => $products->avg('rating'),
            'total_stock' => $products->sum('quantity'),
        ];
    }
}