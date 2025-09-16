<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CustomerAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:customer', ['except' => ['login', 'register']]);
    }

    /**
     * Customer Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $credentials = request(['email', 'password']);

        // Check if customer exists and is active
        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        if (!$customer->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated'
            ], 403);
        }

        if (!$token = auth('customer')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'customer' => $customer,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('customer')->factory()->getTTL() * 60
            ]
        ]);
    }

    /**
     * Customer Registration
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:customers',
            'contact' => 'required|string|max:20',
            'password' => 'required|string|min:6',
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
            'is_active' => true,
        ]);

        // Automatically login after registration
        $token = auth('customer')->login($customer);

        return response()->json([
            'success' => true,
            'message' => 'Customer registered successfully',
            'data' => [
                'customer' => $customer,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('customer')->factory()->getTTL() * 60
            ]
        ], 201);
    }

    /**
     * Get authenticated customer profile
     */
    public function profile()
    {
        $customer = auth('customer')->user();

        return response()->json([
            'success' => true,
            'message' => 'Customer profile retrieved',
            'data' => [
                'customer' => $customer
            ]
        ]);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(Request $request)
    {
        $customer = auth('customer')->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|between:2,100',
            'last_name' => 'sometimes|required|string|between:2,100',
            'email' => 'sometimes|required|string|email|max:100|unique:customers,email,' . $customer->id,
            'contact' => 'sometimes|required|string|max:20',
            'password' => 'sometimes|required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $data = $request->only(['first_name', 'last_name', 'email', 'contact']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $customer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'customer' => $customer
            ]
        ]);
    }

    /**
     * Refresh JWT token
     */
    public function refresh()
    {
        try {
            $token = auth('customer')->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth('customer')->factory()->getTTL() * 60
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token'
            ], 401);
        }
    }

    /**
     * Logout customer
     */
    public function logout()
    {
        auth('customer')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get customer's cart summary
     */
    public function cartSummary()
    {
        $customer = auth('customer')->user();
        $cartSummary = \App\Models\ShoppingCart::getCartSummary($customer->id);

        return response()->json([
            'success' => true,
            'message' => 'Cart summary retrieved',
            'data' => $cartSummary
        ]);
    }
}