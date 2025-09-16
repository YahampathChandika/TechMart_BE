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
| Protected Admin/User Routes (Will add in next phases)
|--------------------------------------------------------------------------
*/

// Product management routes (admin/users with privileges)
Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], function () {
    // Products
    Route::group(['prefix' => 'products'], function () {
        Route::get('/', function () {
            return response()->json(['message' => 'Product list endpoint - coming in Phase 5']);
        });
        Route::post('/', function () {
            return response()->json(['message' => 'Create product endpoint - coming in Phase 5']);
        })->middleware('privilege:can_add_products');
    });
    
    // User management (admin only)
    Route::group(['prefix' => 'users', 'middleware' => 'role:admin'], function () {
        Route::get('/', function () {
            return response()->json(['message' => 'User list endpoint - coming in Phase 6']);
        });
    });
    
    // Customer management (admin/users)
    Route::group(['prefix' => 'customers', 'middleware' => 'role:any'], function () {
        Route::get('/', function () {
            return response()->json(['message' => 'Customer list endpoint - coming in Phase 6']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Public Customer Routes (Will add in next phases)
|--------------------------------------------------------------------------
*/

// Public product browsing
Route::group(['prefix' => 'products'], function () {
    Route::get('/', function () {
        return response()->json(['message' => 'Public product list - coming in Phase 5']);
    });
    Route::get('/{id}', function ($id) {
        return response()->json(['message' => "Product details for ID: $id - coming in Phase 5"]);
    });
});

// Shopping cart routes
Route::group(['prefix' => 'cart', 'middleware' => 'auth:customer'], function () {
    Route::get('/', function () {
        return response()->json(['message' => 'Cart items - coming in Phase 7']);
    });
    Route::post('/add', function () {
        return response()->json(['message' => 'Add to cart - coming in Phase 7']);
    });
});