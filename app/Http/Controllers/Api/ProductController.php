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

        // 11. Profit Margin Filter
        if ($request->filled('min_profit_margin')) {
            $minMargin = $request->min_profit_margin;
            $query->whereRaw('((sell_price - cost_price) / cost_price * 100) >= ?', [$minMargin]);
        }

        // 12. Creator Filter (who created the product - backward compatibility)
        if ($request->filled('created_by')) {
            if (is_array($request->created_by)) {
                $query->whereIn('created_by', $request->created_by);
            } else {
                $createdBy = explode(',', $request->created_by);
                $query->whereIn('created_by', $createdBy);
            }
        }

        // Admin-specific filters
        if ($isAdmin) {
            // 13. Status Filter (active/inactive)
            if ($request->filled('status')) {
                $query->where('is_active', $request->status === 'active');
            }

            // 14. Cost Price Range Filter
            if ($request->filled('min_cost_price')) {
                $query->where('cost_price', '>=', $request->min_cost_price);
            }
            if ($request->filled('max_cost_price')) {
                $query->where('cost_price', '<=', $request->max_cost_price);
            }
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting($query, Request $request, $isAdmin = false)
    {
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['name', 'brand', 'sell_price', 'rating', 'created_at', 'quantity'];
        
        if ($isAdmin) {
            $allowedSorts = array_merge($allowedSorts, ['cost_price', 'is_active', 'updated_at']);
        }

        // Special sorting cases
        switch ($sortBy) {
            case 'profit_margin':
                $query->orderByRaw('((sell_price - cost_price) / cost_price * 100) ' . $sortOrder);
                break;
            case 'alphabetical':
                $query->orderBy('name', 'asc');
                break;
            case 'popularity':
                // Simulate popularity based on rating and recent creation
                $query->orderByRaw('(rating * 0.7 + (DATEDIFF(NOW(), created_at) * -0.01)) DESC');
                break;
            default:
                if (in_array($sortBy, $allowedSorts)) {
                    $query->orderBy($sortBy, $sortOrder);
                }
        }
    }

    /**
     * Get filter aggregates for UI (available options)
     */
    private function getFilterAggregates(Request $request, $isAdmin = false)
    {
        $baseQuery = $isAdmin ? Product::query() : Product::active();

        return [
            'brands' => $baseQuery->select('brand')
                                 ->distinct()
                                 ->orderBy('brand')
                                 ->pluck('brand'),
            'price_range' => [
                'min' => $baseQuery->min('sell_price'),
                'max' => $baseQuery->max('sell_price'),
                'avg' => round($baseQuery->avg('sell_price'), 2)
            ],
            'rating_distribution' => $baseQuery->select('rating', DB::raw('count(*) as count'))
                                              ->groupBy('rating')
                                              ->orderBy('rating', 'desc')
                                              ->get(),
            'stock_levels' => [
                'in_stock' => $baseQuery->where('quantity', '>', 0)->count(),
                'out_of_stock' => $baseQuery->where('quantity', '=', 0)->count(),
                'low_stock' => $baseQuery->where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
                'high_stock' => $baseQuery->where('quantity', '>', 50)->count(),
            ],
            'creators' => $isAdmin ? $baseQuery->with('creator:id,first_name,last_name')
                                             ->get()
                                             ->pluck('creator')
                                             ->unique('id')
                                             ->values() : [],
            'date_range' => [
                'earliest' => $baseQuery->min('created_at'),
                'latest' => $baseQuery->max('created_at')
            ]
        ];
    }

    /**
     * Get currently applied filters for response
     */
    private function getAppliedFilters(Request $request, $isAdmin = false)
    {
        $applied = [];

        $filters = [
            'search', 'brand', 'brands', 'min_price', 'max_price', 'rating', 'min_rating', 'max_rating',
            'in_stock', 'availability', 'min_quantity', 'max_quantity', 'created_from', 'created_to',
            'min_profit_margin', 'created_by', 'sort_by', 'sort_order'
        ];

        if ($isAdmin) {
            $filters = array_merge($filters, ['status', 'min_cost_price', 'max_cost_price']);
        }

        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                $applied[$filter] = $request->get($filter);
            }
        }

        return $applied;
    }

    /**
     * Get filter options for the frontend
     */
    public function getFilterOptions()
    {
        $brands = Product::active()
                        ->select('brand', DB::raw('count(*) as count'))
                        ->groupBy('brand')
                        ->orderBy('brand')
                        ->get();

        $priceRange = [
            'min' => Product::active()->min('sell_price'),
            'max' => Product::active()->max('sell_price'),
            'step' => 10 // Price step for slider
        ];

        $ratings = Product::active()
                         ->select('rating', DB::raw('count(*) as count'))
                         ->groupBy('rating')
                         ->orderBy('rating', 'desc')
                         ->get();

        $stockLevels = [
            'in_stock' => Product::active()->where('quantity', '>', 0)->count(),
            'out_of_stock' => Product::active()->where('quantity', '=', 0)->count(),
            'low_stock' => Product::active()->where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
        ];

        $creators = Product::active()
                          ->with('creator:id,first_name,last_name')
                          ->get()
                          ->pluck('creator')
                          ->unique('id')
                          ->values();

        return response()->json([
            'success' => true,
            'message' => 'Filter options retrieved successfully',
            'data' => [
                'brands' => $brands,
                'price_range' => $priceRange,
                'ratings' => $ratings,
                'stock_levels' => $stockLevels,
                'creators' => $creators,
                'availability_options' => [
                    ['value' => 'in_stock', 'label' => 'In Stock'],
                    ['value' => 'out_of_stock', 'label' => 'Out of Stock'],
                    ['value' => 'low_stock', 'label' => 'Low Stock (≤10)'],
                    ['value' => 'high_stock', 'label' => 'High Stock (>50)'],
                ],
                'sort_options' => [
                    ['value' => 'name', 'label' => 'Name'],
                    ['value' => 'sell_price', 'label' => 'Price'],
                    ['value' => 'rating', 'label' => 'Rating'],
                    ['value' => 'created_at', 'label' => 'Newest'],
                    ['value' => 'quantity', 'label' => 'Stock'],
                    ['value' => 'popularity', 'label' => 'Popularity'],
                    ['value' => 'alphabetical', 'label' => 'A-Z'],
                ]
            ]
        ]);
    }

    /**
     * Advanced search with multiple criteria
     */
    public function advancedSearch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'nullable|string|max:255',
            'filters' => 'nullable|array',
            'filters.brands' => 'nullable|array',
            'filters.price_range' => 'nullable|array',
            'filters.price_range.min' => 'nullable|numeric|min:0',
            'filters.price_range.max' => 'nullable|numeric|min:0',
            'filters.rating' => 'nullable|integer|min:1|max:5',
            'filters.availability' => 'nullable|in:in_stock,out_of_stock,low_stock,high_stock',
            'filters.date_range' => 'nullable|array',
            'filters.date_range.from' => 'nullable|date',
            'filters.date_range.to' => 'nullable|date',
            'sort_by' => 'nullable|in:name,brand,sell_price,rating,created_at,quantity,popularity,alphabetical',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $query = Product::active()->with('creator:id,first_name,last_name');

        // Apply filters from the structured request
        if ($request->filled('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('brand', 'LIKE', "%{$searchTerm}%");
            });
        }

        $filters = $request->get('filters', []);

        if (!empty($filters['brands'])) {
            $query->whereIn('brand', $filters['brands']);
        }

        if (!empty($filters['price_range'])) {
            if (isset($filters['price_range']['min'])) {
                $query->where('sell_price', '>=', $filters['price_range']['min']);
            }
            if (isset($filters['price_range']['max'])) {
                $query->where('sell_price', '<=', $filters['price_range']['max']);
            }
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', '>=', $filters['rating']);
        }

        if (!empty($filters['availability'])) {
            switch ($filters['availability']) {
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

        if (!empty($filters['date_range'])) {
            if (isset($filters['date_range']['from'])) {
                $query->where('created_at', '>=', $filters['date_range']['from']);
            }
            if (isset($filters['date_range']['to'])) {
                $query->where('created_at', '<=', $filters['date_range']['to'] . ' 23:59:59');
            }
        }

        // Apply sorting
        $this->applySorting($query, $request);

        // Pagination
        $perPage = min($request->get('per_page', 12), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Advanced search completed successfully',
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'search_summary' => [
                'query' => $request->get('query'),
                'filters_applied' => count(array_filter($filters)),
                'total_results' => $products->total()
            ]
        ]);
    }

    /**
     * Get search suggestions based on partial input
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        // Product name suggestions
        $productSuggestions = Product::active()
                                    ->where('name', 'LIKE', "%{$query}%")
                                    ->select('name')
                                    ->distinct()
                                    ->limit(5)
                                    ->pluck('name');

        // Brand suggestions
        $brandSuggestions = Product::active()
                                  ->where('brand', 'LIKE', "%{$query}%")
                                  ->select('brand')
                                  ->distinct()
                                  ->limit(3)
                                  ->pluck('brand');

        // Description keyword suggestions
        $keywordSuggestions = Product::active()
                                    ->where('description', 'LIKE', "%{$query}%")
                                    ->select('name', 'description')
                                    ->limit(3)
                                    ->get()
                                    ->map(function($product) use ($query) {
                                        // Extract relevant words from description
                                        $words = explode(' ', strtolower($product->description));
                                        $relevantWords = array_filter($words, function($word) use ($query) {
                                            return strpos($word, strtolower($query)) !== false && strlen($word) > 3;
                                        });
                                        return array_slice($relevantWords, 0, 2);
                                    })
                                    ->flatten()
                                    ->unique()
                                    ->values();

        return response()->json([
            'success' => true,
            'message' => 'Search suggestions retrieved',
            'data' => [
                'products' => $productSuggestions,
                'brands' => $brandSuggestions,
                'keywords' => $keywordSuggestions->take(3),
            ]
        ]);
    }

    /**
     * Get trending searches and popular products
     */
    public function trendingSearches()
    {
        // In a real app, you'd track search queries in a separate table
        // For now, we'll return popular brands and high-rated products
        
        $popularBrands = Product::active()
                               ->select('brand', DB::raw('count(*) as product_count'), DB::raw('avg(rating) as avg_rating'))
                               ->groupBy('brand')
                               ->orderBy('product_count', 'desc')
                               ->orderBy('avg_rating', 'desc')
                               ->take(5)
                               ->get();

        $trendingProducts = Product::active()
                                  ->where('rating', '>=', 4)
                                  ->orderBy('created_at', 'desc')
                                  ->take(6)
                                  ->get(['id', 'name', 'brand', 'sell_price', 'rating', 'image_path']);

        $popularSearchTerms = [
            'smartphone', 'laptop', 'headphones', 'gaming', 'wireless', 
            'bluetooth', 'accessories', 'charger', 'case', 'tablet'
        ];

        return response()->json([
            'success' => true,
            'message' => 'Trending data retrieved',
            'data' => [
                'popular_brands' => $popularBrands,
                'trending_products' => $trendingProducts,
                'popular_searches' => $popularSearchTerms,
            ]
        ]);
    }

    /**
     * Product analytics for admin
     */
    public function analytics(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30));
        $dateTo = $request->get('date_to', now());

        $analytics = [
            'total_products' => Product::count(),
            'active_products' => Product::active()->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            
            'stock_analysis' => [
                'in_stock' => Product::active()->where('quantity', '>', 0)->count(),
                'out_of_stock' => Product::active()->where('quantity', '=', 0)->count(),
                'low_stock' => Product::active()->where('quantity', '>', 0)->where('quantity', '<=', 10)->count(),
                'overstocked' => Product::active()->where('quantity', '>', 100)->count(),
            ],
            
            'price_analysis' => [
                'avg_sell_price' => Product::active()->avg('sell_price'),
                'avg_cost_price' => Product::active()->avg('cost_price'),
                'avg_profit_margin' => Product::active()
                                             ->selectRaw('AVG((sell_price - cost_price) / cost_price * 100) as margin')
                                             ->value('margin'),
            ],
            
            'rating_distribution' => Product::active()
                                           ->select('rating', DB::raw('count(*) as count'))
                                           ->groupBy('rating')
                                           ->orderBy('rating')
                                           ->get(),
            
            'brand_performance' => Product::active()
                                         ->select(
                                             'brand',
                                             DB::raw('count(*) as count'),
                                             DB::raw('avg(rating) as avg_rating'),
                                             DB::raw('avg(sell_price) as avg_price')
                                         )
                                         ->groupBy('brand')
                                         ->orderBy('count', 'desc')
                                         ->take(10)
                                         ->get(),
            
            'recent_additions' => Product::whereBetween('created_at', [$dateFrom, $dateTo])
                                        ->select(
                                            DB::raw('DATE(created_at) as date'),
                                            DB::raw('count(*) as count')
                                        )
                                        ->groupBy('date')
                                        ->orderBy('date')
                                        ->get(),
            
            'top_rated_products' => Product::active()
                                          ->where('rating', '>=', 4)
                                          ->orderBy('rating', 'desc')
                                          ->orderBy('created_at', 'desc')
                                          ->take(5)
                                          ->get(['id', 'name', 'brand', 'rating', 'sell_price']),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Product analytics retrieved',
            'data' => $analytics
        ]);
    }

    /**
     * Export products with filters applied
     */
    public function exportProducts(Request $request)
    {
        $query = Product::with('creator:id,first_name,last_name');
        
        // Apply same filters as search
        $this->applySearchFilters($query, $request, true);
        
        $products = $query->get();
        
        $exportData = $products->map(function ($product) {
            return [
                'ID' => $product->id,
                'Name' => $product->name,
                'Brand' => $product->brand,
                'Cost Price' => $product->cost_price,
                'Sell Price' => $product->sell_price,
                'Quantity' => $product->quantity,
                'Rating' => $product->rating,
                'Status' => $product->is_active ? 'Active' : 'Inactive',
                'Created By' => $product->creator ? $product->creator->first_name . ' ' . $product->creator->last_name : 'N/A',
                'Created At' => $product->created_at->format('Y-m-d H:i:s'),
                'Profit Margin %' => round((($product->sell_price - $product->cost_price) / $product->cost_price) * 100, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Products exported successfully',
            'data' => $exportData,
            'meta' => [
                'total_exported' => $exportData->count(),
                'export_timestamp' => now()->toISOString(),
                'filters_applied' => $this->getAppliedFilters($request, true)
            ]
        ]);
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'update_data' => 'required|array',
            'update_data.is_active' => 'sometimes|boolean',
            'update_data.rating' => 'sometimes|integer|min:1|max:5',
            'update_data.quantity' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $updated = Product::whereIn('id', $request->product_ids)
                         ->update($request->update_data);

        return response()->json([
            'success' => true,
            'message' => "{$updated} products updated successfully",
            'data' => [
                'updated_count' => $updated,
                'product_ids' => $request->product_ids,
                'update_data' => $request->update_data
            ]
        ]);
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Get products before deletion for cleanup
        $products = Product::whereIn('id', $request->product_ids)->get();
        
        // Delete associated images
        foreach ($products as $product) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                Storage::disk('public')->delete($product->image_path);
            }
        }

        $deleted = Product::whereIn('id', $request->product_ids)->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} products deleted successfully",
            'data' => [
                'deleted_count' => $deleted,
                'product_ids' => $request->product_ids
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