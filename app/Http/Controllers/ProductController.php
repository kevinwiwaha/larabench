<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * 
     * Read-heavy endpoint for benchmarking SELECT performance.
     * Supports filtering and sorting to simulate realistic queries.
     * 
     * Query params:
     * - search: string (filters by name using LIKE)
     * - min_price: numeric
     * - max_price: numeric
     * - sort: recent|price_asc|price_desc
     * - per_page: int (default 20)
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter by name (performance note: LIKE with leading wildcard can't use index)
        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        // Price range filtering (uses price index)
        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        // Sorting (price and created_at are indexed)
        $sort = $request->query('sort', 'recent');

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        // Pagination (adjust per_page to simulate different load patterns)
        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100); // Clamp between 1-100

        return $query->paginate($perPage);
    }
}

