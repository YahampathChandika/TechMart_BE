<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerAuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Test route
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'TechMart API is working!',
        'version' => '1.0.0'
    ]);
});

/*
|--------------------------------------------------------------------------
| Admin/User Authentication Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'auth'], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    
    Route::group(['middleware' => 'auth:api'], function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });
});

/*
|--------------------------------------------------------------------------
| Customer Authentication Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'customer'], function () {
    Route::post('/login', [CustomerAuthController::class, 'login']);
    Route::post('/register', [CustomerAuthController::class, 'register']);
    
    Route::group(['middleware' => 'auth:customer'], function () {
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
        Route::post('/refresh', [CustomerAuthController::class, 'refresh']);
        Route::get('/profile', [CustomerAuthController::class, 'profile']);
        Route::put('/profile', [CustomerAuthController::class, 'updateProfile']);
        Route::get('/cart-summary', [CustomerAuthController::class, 'cartSummary']);
    });
});

/*
|--------------------------------------------------------------------------
| Public Product Routes (Customer/Public Access)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CartController;

// Public product browsing
Route::group(['prefix' => 'products'], function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/brands', [ProductController::class, 'brands']);
    Route::get('/{id}', [ProductController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Protected Admin/User Routes
|--------------------------------------------------------------------------
*/

// Product management routes (admin/users with privileges)
Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], function () {
    
    // Product Management
    Route::group(['prefix' => 'products'], function () {
        // All authenticated users can view products
        Route::get('/', [ProductController::class, 'adminIndex']);
        Route::get('/statistics', [ProductController::class, 'statistics']);
        Route::get('/{id}', [ProductController::class, 'show']);
        
        // Privilege-based product operations
        Route::post('/', [ProductController::class, 'store'])->middleware('privilege:can_add_products');
        Route::post('/{id}', [ProductController::class, 'update'])->middleware('privilege:can_update_products');
        Route::put('/{id}', [ProductController::class, 'update'])->middleware('privilege:can_update_products');
        Route::patch('/{id}/toggle-status', [ProductController::class, 'toggleStatus'])->middleware('privilege:can_update_products');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->middleware('privilege:can_delete_products');
        Route::delete('/{id}/image', [ProductController::class, 'deleteImage'])->middleware('privilege:can_update_products');
    });
    
    // User management (admin only)
    Route::group(['prefix' => 'users', 'middleware' => 'role:admin'], function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/statistics', [UserController::class, 'statistics']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        
        // User privilege management
        Route::get('/{id}/privileges', [UserController::class, 'getPrivileges']);
        Route::put('/{id}/privileges', [UserController::class, 'updatePrivileges']);
    });
    
    // Customer management (admin/users can access)
    Route::group(['prefix' => 'customers', 'middleware' => 'role:any'], function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/statistics', [CustomerController::class, 'statistics']);
        Route::get('/top-customers', [CustomerController::class, 'topCustomers']);
        Route::get('/export', [CustomerController::class, 'export']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::patch('/{id}/toggle-status', [CustomerController::class, 'toggleStatus']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
        
        // Customer cart management
        Route::get('/{id}/cart', [CustomerController::class, 'getCart']);
        Route::delete('/{id}/cart', [CustomerController::class, 'clearCart']);
    });
});

// Shopping cart routes (customer authentication required)
Route::group(['prefix' => 'cart', 'middleware' => 'auth:customer'], function () {
    // Cart viewing and summary
    Route::get('/', [CartController::class, 'index']);
    Route::get('/summary', [CartController::class, 'summary']);
    Route::get('/count', [CartController::class, 'count']);
    Route::get('/validate', [CartController::class, 'validateCart']);
    
    // Adding products to cart
    Route::post('/add', [CartController::class, 'addToCart']);
    Route::post('/quick-add', [CartController::class, 'quickAdd']);
    
    // Cart item management
    Route::put('/items/{itemId}', [CartController::class, 'updateQuantity']);
    Route::patch('/bulk-update', [CartController::class, 'bulkUpdate']);
    Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
    
    // Cart operations
    Route::delete('/clear', [CartController::class, 'clearCart']);
    
    // Product cart status
    Route::get('/check-product/{productId}', [CartController::class, 'checkProduct']);
});