<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get customer's full name
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Relationship: Customer has many cart items
     */
    public function cartItems()
    {
        return $this->hasMany(ShoppingCart::class);
    }

    /**
     * Get customer's cart with product details
     */
    public function cartWithProducts()
    {
        return $this->cartItems()->with('product');
    }

    /**
     * Get total cart items count
     */
    public function getCartItemsCountAttribute()
    {
        return $this->cartItems()->sum('quantity');
    }

    /**
     * Get total cart value
     */
    public function getCartTotalAttribute()
    {
        return $this->cartItems()->with('product')->get()->sum(function ($item) {
            return $item->quantity * $item->product->sell_price;
        });
    }

    /**
     * Add product to cart (convenience method)
     */
    public function addToCart($productId, $quantity = 1)
    {
        return ShoppingCart::addToCart($this->id, $productId, $quantity);
    }

    /**
     * Remove product from cart
     */
    public function removeFromCart($productId)
    {
        return ShoppingCart::where('customer_id', $this->id)
                          ->where('product_id', $productId)
                          ->delete();
    }

    /**
     * Check if product is in cart
     */
    public function hasInCart($productId)
    {
        return ShoppingCart::where('customer_id', $this->id)
                          ->where('product_id', $productId)
                          ->exists();
    }

    /**
     * Get quantity of specific product in cart
     */
    public function getCartQuantity($productId)
    {
        $item = ShoppingCart::where('customer_id', $this->id)
                           ->where('product_id', $productId)
                           ->first();
        
        return $item ? $item->quantity : 0;
    }

    /**
     * Clear entire cart
     */
    public function clearCart()
    {
        return ShoppingCart::clearCartForCustomer($this->id);
    }

    /**
     * Get cart summary with details
     */
    public function getCartSummary()
    {
        return ShoppingCart::getCartSummary($this->id);
    }
}