<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * POST /api/orders
     * 
     * Write-heavy endpoint for benchmarking INSERT performance.
     * Creates a single order record with race condition handling.
     * 
     * Request body:
     * {
     *   "user_id": 1,
     *   "product_id": 1,
     *   "quantity": 2
     * }
     * 
     * Implementation: Laravel Query Builder
     * - Clean, readable syntax
     * - Low overhead (no model hydration)
     * - Database agnostic
     * - ~15% faster than Eloquent
     */
    public function store(Request $request)
    {
        // Validation adds overhead but is realistic
        $validated = $request->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:100',
        ]);

        // ============================================================
        // CHOOSE STRATEGY
        // ============================================================
        
        // Strategy 1: Basic transaction (default)
        return $this->storeWithAtomicStock($validated);
        
        // Strategy 2: Pessimistic locking (uncomment to use)
        // return $this->storeWithLocking($validated);
        
        // Strategy 3: Optimistic with retry (uncomment to use)
        // return $this->storeWithRetry($validated);
        
        // Strategy 4: Atomic stock decrement (uncomment to use)
        // return $this->storeWithAtomicStock($validated);
    }

    private function storeWithAtomicStock(array $validated)
{
    $result = DB::transaction(function () use ($validated) {
        $userId    = $validated['user_id'];
        $productId = $validated['product_id'];
        $quantity  = (int) $validated['quantity'];

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than 0.');
        }

        // 1) ATOMIC CHECK + DECREMENT
        // This is the critical part: no prior stock read, no race condition.
        $affected = DB::table('products')
            ->where('id', $productId)
            ->where('stock', '>=', $quantity)  // atomic guard
            ->update([
                'stock'      => DB::raw('stock - ' . $quantity),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            // Either product doesn't exist OR stock < quantity
            // If you want nicer messages, you can do a follow-up SELECT here.
            throw new \Exception('Insufficient stock or product not found.');
        }

        // 2) Fetch product data AFTER stock is safely decremented
        // (we only need price here; stock is no longer important)
        $product = DB::table('products')
            ->where('id', $productId)
            ->first(['price']);

        if (!$product) {
            // This should be practically impossible if the UPDATE above succeeded,
            // but we keep it for safety.
            throw new \Exception('Product not found after stock update.');
        }

        $unitPrice  = $product->price;
        $totalPrice = $unitPrice * $quantity;
        $now        = now();

        // 3) Create order
        $orderId = DB::table('orders')->insertGetId([
            'user_id'     => $userId,
            'product_id'  => $productId,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total_price' => $totalPrice,
            'status'      => 'paid',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return [
            'id'          => $orderId,
            'user_id'     => $userId,
            'product_id'  => $productId,
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total_price' => $totalPrice,
            'status'      => 'paid',
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
    });

    return response()->json($result, 201);
}

}
