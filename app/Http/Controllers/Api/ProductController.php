<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class ProductController extends Controller
{
    /**
     * Get public product list (for customers/public)
     */
    public function index(Request $request)
    {
        $query = Product::active()->with('creator:id,first_name,last_name');

        // Search filters (4+ as required)
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('brand')) {
            $query->byBrand($request->brand);
        }

        if ($request->filled('min_price')) {
            $query->priceRange($request->min_price, null);
        }

        if ($request->filled('max_price')) {
            $query->priceRange(null, $request->max_price);
        }

        if ($request->filled('rating')) {
            $query->byRating($request->rating);
        }

        if ($request->filled('in_stock')) {
            if ($request->in_stock === 'true') {
                $query->inStock();
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['name', 'sell_price', 'rating', 'created_at', 'quantity'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 12), 50);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ]
        ]);
    }

    /**
     * Get all products for admin (including inactive)
     */
    public function adminIndex(Request $request)
    {
        $query = Product::with('creator:id,first_name,last_name');

        // All the same filters as public, plus status filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('brand')) {
            $query->byBrand($request->brand);
        }

        if ($request->filled('min_price')) {
            $query->priceRange($request->min_price, null);
        }

        if ($request->filled('max_price')) {
            $query->priceRange(null, $request->max_price);
        }

        if ($request->filled('rating')) {
            $query->byRating($request->rating);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['name', 'sell_price', 'rating', 'created_at', 'quantity', 'brand'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Admin products retrieved successfully',
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ]
        ]);
    }

    /**
     * Get single product details
     */
    public function show($id)
    {
        $product = Product::with('creator:id,first_name,last_name')->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // For public access, only show active products
        $user = auth('api')->user();
        if (!$user && !$product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => $product
        ]);
    }

    /**
     * Create new product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Validate sell price is greater than cost price
        if ($request->sell_price <= $request->cost_price) {
            return response()->json([
                'success' => false,
                'message' => 'Sell price must be greater than cost price'
            ], 400);
        }

        $data = $request->only([
            'brand', 'name', 'quantity', 'cost_price', 
            'sell_price', 'description', 'rating'
        ]);

        $data['is_active'] = $request->get('is_active', true);
        $data['created_by'] = auth('api')->id();

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $this->uploadProductImage($request->file('image'));
            $data['image_path'] = $imagePath;
        }

        $product = Product::create($data);
        $product->load('creator:id,first_name,last_name');

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'brand' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'quantity' => 'sometimes|required|integer|min:0',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'sell_price' => 'sometimes|required|numeric|min:0',
            'description' => 'sometimes|required|string',
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $data = $request->only([
            'brand', 'name', 'quantity', 'cost_price', 
            'sell_price', 'description', 'rating', 'is_active'
        ]);

        // Validate sell price vs cost price if both are provided
        $costPrice = $request->get('cost_price', $product->cost_price);
        $sellPrice = $request->get('sell_price', $product->sell_price);
        
        if ($sellPrice <= $costPrice) {
            return response()->json([
                'success' => false,
                'message' => 'Sell price must be greater than cost price'
            ], 400);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            
            $imagePath = $this->uploadProductImage($request->file('image'));
            $data['image_path'] = $imagePath;
        }

        $product->update($data);
        $product->load('creator:id,first_name,last_name');

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Delete image if exists
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Toggle product status (activate/deactivate)
     */
    public function toggleStatus($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $product->update(['is_active' => !$product->is_active]);

        $status = $product->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Product {$status} successfully",
            'data' => $product
        ]);
    }

    /**
     * Get product statistics (for admin dashboard)
     */
    public function statistics()
    {
        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::active()->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'out_of_stock' => Product::where('quantity', 0)->count(),
            'low_stock' => Product::where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
            'total_value' => Product::active()->sum('sell_price'),
            'average_rating' => round(Product::active()->avg('rating'), 2),
            'brands_count' => Product::active()->distinct('brand')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Product statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get all brands (for filter dropdown)
     */
    public function brands()
    {
        $brands = Product::active()
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return response()->json([
            'success' => true,
            'message' => 'Brands retrieved successfully',
            'data' => $brands
        ]);
    }

    /**
     * Handle product image upload
     */
    private function uploadProductImage($image)
    {
        // Create unique filename
        $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
        $path = 'products/' . $filename;

        // Resize and optimize image
        $img = Image::make($image);
        
        // Resize to max 800x800 while maintaining aspect ratio
        $img->resize(800, 800, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        // Reduce quality to optimize file size
        $img->encode($image->getClientOriginalExtension(), 80);

        // Save to storage
        Storage::disk('public')->put($path, $img->stream());

        return $path;
    }

    /**
     * Delete product image
     */
    public function deleteImage($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        if (!$product->image_path) {
            return response()->json([
                'success' => false,
                'message' => 'Product has no image'
            ], 400);
        }

        // Delete image file
        Storage::disk('public')->delete($product->image_path);
        
        // Remove image path from database
        $product->update(['image_path' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Product image deleted successfully'
        ]);
    }
}