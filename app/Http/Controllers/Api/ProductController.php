<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class ProductController extends Controller
{
    /**
     * Get public product list with enhanced search filters (for customers/public)
     */
    public function index(Request $request)
    {
        $query = Product::active()->with('creator:id,first_name,last_name');

        // Apply all search filters
        $this->applySearchFilters($query, $request);

        // Sorting with enhanced options
        $this->applySorting($query, $request);

        // Pagination
        $perPage = min($request->get('per_page', 12), 50);
        $products = $query->paginate($perPage);

        // Add aggregated data for filters
        $filterData = $this->getFilterAggregates($request);

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
            ],
            'filters' => $filterData,
            'applied_filters' => $this->getAppliedFilters($request)
        ]);
    }

    /**
     * Get enhanced search filters for admin (including inactive products)
     */
    public function adminIndex(Request $request)
    {
        $query = Product::with('creator:id,first_name,last_name');

        // Apply all search filters (including admin-specific ones)
        $this->applySearchFilters($query, $request, true);

        // Enhanced sorting for admin
        $this->applySorting($query, $request, true);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $products = $query->paginate($perPage);

        // Enhanced filter data for admin
        $filterData = $this->getFilterAggregates($request, true);

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
            ],
            'filters' => $filterData,
            'applied_filters' => $this->getAppliedFilters($request, true)
        ]);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'is_active' => 'nullable|string', // Changed to handle FormData
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Added webp, increased size
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $productData = [
                'name' => $request->name,
                'description' => $request->description,
                'brand' => $request->brand,
                'category' => $request->category,
                'cost_price' => $request->cost_price,
                'sell_price' => $request->sell_price,
                'quantity' => $request->quantity,
                'rating' => $request->rating ?? 1,
            ];

            // Handle is_active (FormData sends as string)
            if ($request->has('is_active')) {
                $productData['is_active'] = $request->get('is_active') === '1' || $request->get('is_active') === true;
            } else {
                $productData['is_active'] = true;
            }

            $productData['created_by'] = auth('api')->id();

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                
                // Store in public/images/products directory
                $imagePath = $image->storeAs('images/products', $imageName, 'public');
                $productData['image_path'] = 'storage/' . $imagePath; // Changed to image_path
            }

            $product = Product::create($productData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => [
                    'product' => $product->load('creator:id,first_name,last_name')
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified product
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

        // Handle Laravel's _method field for FormData requests
        $isFormData = $request->has('_method') && $request->get('_method') === 'PUT';

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'rating' => 'nullable|numeric|min:0|max:5',
            'is_active' => $isFormData ? 'nullable|string' : 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $productData = [
                'name' => $request->name,
                'description' => $request->description,
                'brand' => $request->brand,
                'category' => $request->category,
                'cost_price' => $request->cost_price,
                'sell_price' => $request->sell_price,
                'quantity' => $request->quantity,
                'rating' => $request->rating ?? 1,
            ];

            // Handle is_active (different handling for FormData vs JSON)
            if ($request->has('is_active')) {
                if ($isFormData) {
                    // FormData - convert string to boolean
                    $productData['is_active'] = $request->get('is_active') === '1';
                } else {
                    // JSON - direct boolean
                    $productData['is_active'] = $request->get('is_active');
                }
            }

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($product->image_path && Storage::disk('public')->exists(str_replace('storage/', '', $product->image_path))) {
                    Storage::disk('public')->delete(str_replace('storage/', '', $product->image_path));
                }

                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                
                // Store in public/images/products directory
                $imagePath = $image->storeAs('images/products', $imageName, 'public');
                $productData['image_path'] = 'storage/' . $imagePath; // Changed to image_path
            }

            $product->update($productData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => [
                    'product' => $product->fresh()->load('creator:id,first_name,last_name')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product from storage
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

        try {
            DB::beginTransaction();

            // Check if product is in any carts
            $cartCount = $product->cartItems()->count();
            if ($cartCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete product. It's currently in {$cartCount} shopping cart(s). Please remove it from all carts first."
                ], 400);
            }

            // Delete associated image if exists
            if ($product->image_path && Storage::disk('public')->exists(str_replace('storage/', '', $product->image_path))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $product->image_path));
            }

            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle product active status
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

        try {
            $product->update([
                'is_active' => !$product->is_active
            ]);

            $status = $product->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "Product {$status} successfully",
                'data' => [
                    'product' => $product->fresh()->load('creator:id,first_name,last_name')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle product status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products statistics for admin dashboard
     */
    public function statistics()
    {
        $stats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'inactive' => Product::where('is_active', false)->count(),
            'out_of_stock' => Product::where('quantity', 0)->count(),
            'low_stock' => Product::where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
            'high_stock' => Product::where('quantity', '>', 50)->count(),
            'total_value' => Product::where('is_active', true)->sum(DB::raw('sell_price * quantity')),
            'avg_price' => Product::where('is_active', true)->avg('sell_price'),
            'avg_rating' => Product::where('is_active', true)->where('rating', '>', 0)->avg('rating'),
            'brands_count' => Product::distinct('brand')->count(),
            'categories_count' => Product::whereNotNull('category')->distinct('category')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Product statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Apply comprehensive search filters to the query
     */
    private function applySearchFilters($query, Request $request, $isAdmin = false)
    {
        // 1. Text Search (name, description, brand)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('brand', 'LIKE', "%{$searchTerm}%");
            });
        }

        // 2. Brand Filter (single brand - backward compatibility)
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        // 3. Brand Filter (multiple brands)
        if ($request->filled('brands')) {
            $brands = is_array($request->brands) ? $request->brands : explode(',', $request->brands);
            $query->whereIn('brand', $brands);
        }

        // 4. Price Range Filter
        if ($request->filled('min_price')) {
            $query->where('sell_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('sell_price', '<=', $request->max_price);
        }

        // 5. Rating Filter (minimum rating - backward compatibility)
        if ($request->filled('rating')) {
            $query->where('rating', '>=', $request->rating);
        }

        // 6. Rating Range Filter
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }
        if ($request->filled('max_rating')) {
            $query->where('rating', '<=', $request->max_rating);
        }

        // 7. Stock/Availability Filter (backward compatibility)
        if ($request->filled('in_stock')) {
            if ($request->in_stock === 'true') {
                $query->where('quantity', '>', 0);
            }
        }

        // 8. Enhanced Availability Filter
        if ($request->filled('availability')) {
            switch ($request->availability) {
                case 'in_stock':
                    $query->where('quantity', '>', 0);
                    break;
                case 'out_of_stock':
                    $query->where('quantity', '=', 0);
                    break;
                case 'low_stock':
                    $query->where('quantity', '>', 0)->where('quantity', '<=', 10);
                    break;
                case 'high_stock':
                    $query->where('quantity', '>', 50);
                    break;
            }
        }

        // 9. Quantity Range Filter
        if ($request->filled('min_quantity')) {
            $query->where('quantity', '>=', $request->min_quantity);
        }
        if ($request->filled('max_quantity')) {
            $query->where('quantity', '<=', $request->max_quantity);
        }

        // 10. Date Range Filter (created date)
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->created_to . ' 23:59:59');
        }

        // Admin-specific filters
        if ($isAdmin) {
            // 11. Status Filter (active/inactive)
            if ($request->filled('status')) {
                $query->where('is_active', $request->status === 'active');
            }

            // 12. Cost Price Range Filter
            if ($request->filled('min_cost_price')) {
                $query->where('cost_price', '>=', $request->min_cost_price);
            }
            if ($request->filled('max_cost_price')) {
                $query->where('cost_price', '<=', $request->max_cost_price);
            }

            // 13. Creator Filter
            if ($request->filled('created_by')) {
                if (is_array($request->created_by)) {
                    $query->whereIn('created_by', $request->created_by);
                } else {
                    $createdBy = explode(',', $request->created_by);
                    $query->whereIn('created_by', $createdBy);
                }
            }
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting($query, Request $request, $isAdmin = false)
    {
        $sort = $request->get('sort', 'created_at');
        $sortOrder = 'desc';

        // Map frontend sort values to database columns
        switch ($sort) {
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'price_low':
                $query->orderBy('sell_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('sell_price', 'desc');
                break;
            case 'rating':
                $query->orderBy('rating', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'popular':
                // Simulate popularity based on rating
                $query->orderBy('rating', 'desc')
                      ->orderBy('created_at', 'desc');
                break;
            case 'alphabetical':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Secondary sort by ID for consistency
        $query->orderBy('id', 'desc');
    }

    /**
     * Get filter aggregates for UI (available options) - FIXED VERSION
     */
    private function getFilterAggregates(Request $request, $isAdmin = false)
    {
        // Base query for filter aggregates (separate from main query)
        $baseQuery = $isAdmin ? Product::query() : Product::active();

        try {
            // Get unique brands
            $brands = $baseQuery->select('brand')
                              ->distinct()
                              ->whereNotNull('brand')
                              ->orderBy('brand', 'asc')
                              ->pluck('brand')
                              ->toArray();

            // Get price range
            $priceRange = $baseQuery->selectRaw('MIN(sell_price) as min, MAX(sell_price) as max')
                                   ->first();

            // Get ratings - FIXED: Remove conflicting ORDER BY
            $ratings = $baseQuery->select('rating', DB::raw('count(*) as count'))
                                ->whereNotNull('rating')
                                ->groupBy('rating')
                                ->orderBy('rating', 'desc') // Only order by grouped column
                                ->get()
                                ->keyBy('rating')
                                ->toArray();

            // Get stock levels
            $stockLevels = [
                'in_stock' => $baseQuery->where('quantity', '>', 0)->count(),
                'out_of_stock' => $baseQuery->where('quantity', '=', 0)->count(),
                'low_stock' => $baseQuery->where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
                'high_stock' => $baseQuery->where('quantity', '>', 50)->count(),
            ];

            return [
                'brands' => $brands,
                'price_range' => [
                    'min' => $priceRange->min ?? 0,
                    'max' => $priceRange->max ?? 1000,
                ],
                'ratings' => $ratings,
                'stock_levels' => $stockLevels,
                'sort_options' => [
                    ['value' => 'name', 'label' => 'Name A-Z'],
                    ['value' => 'price_low', 'label' => 'Price: Low to High'],
                    ['value' => 'price_high', 'label' => 'Price: High to Low'],
                    ['value' => 'rating', 'label' => 'Highest Rated'],
                    ['value' => 'newest', 'label' => 'Newest First'],
                    ['value' => 'popular', 'label' => 'Most Popular'],
                ],
            ];
        } catch (\Exception $e) {
            // Fallback data if aggregate queries fail
            return [
                'brands' => [],
                'price_range' => ['min' => 0, 'max' => 1000],
                'ratings' => [],
                'stock_levels' => ['in_stock' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'high_stock' => 0],
                'sort_options' => [
                    ['value' => 'name', 'label' => 'Name A-Z'],
                    ['value' => 'newest', 'label' => 'Newest First'],
                ],
            ];
        }
    }

    /**
     * Get applied filters summary
     */
    private function getAppliedFilters(Request $request, $isAdmin = false)
    {
        $applied = [];

        if ($request->filled('search')) {
            $applied['search'] = $request->search;
        }

        if ($request->filled('brand')) {
            $applied['brand'] = $request->brand;
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $applied['price_range'] = [
                'min' => $request->min_price,
                'max' => $request->max_price,
            ];
        }

        if ($request->filled('rating')) {
            $applied['rating'] = $request->rating;
        }

        if ($request->filled('in_stock')) {
            $applied['in_stock'] = $request->in_stock === 'true';
        }

        if ($request->filled('sort')) {
            $applied['sort'] = $request->sort;
        }

        return $applied;
    }

    /**
     * Get single product
     */
    public function show($id)
    {
        try {
            $product = Product::with('creator:id,first_name,last_name')
                             ->where('id', $id)
                             ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // For admin endpoints, include extra data
            if (request()->is('api/admin/*')) {
                $product->cart_count = $product->cartItems()->count();
                $product->total_in_carts = $product->cartItems()->sum('quantity');
            } else {
                // For public endpoints, only show active products
                if (!$product->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found or inactive',
                    ], 404);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available brands
     */
    public function brands()
    {
        try {
            $brands = Product::active()
                           ->select('brand')
                           ->distinct()
                           ->whereNotNull('brand')
                           ->orderBy('brand', 'asc')
                           ->pluck('brand');

            return response()->json([
                'success' => true,
                'message' => 'Brands retrieved successfully',
                'data' => $brands,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brands',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get filter options
     */
    public function getFilterOptions(Request $request)
    {
        try {
            $filterData = $this->getFilterAggregates($request);

            return response()->json([
                'success' => true,
                'message' => 'Filter options retrieved successfully',
                'data' => $filterData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filter options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Advanced search
     */
    public function advancedSearch(Request $request)
    {
        return $this->index($request);
    }
}