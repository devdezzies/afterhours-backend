<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page'      => ['sometimes', 'integer', 'min:1'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:50'],
            'category'  => ['sometimes', Rule::in([
                'peripherals', 'furniture', 'desk_accessories', 'audio', 'eyewear'
            ])],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'keywords'  => ['sometimes', 'string', 'max:500'],
        ]);

        $query = Product::query();

        // ── Filter: category ──────────────────────────────────────────────────
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // ── Filter: max_price ─────────────────────────────────────────────────
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        if ($request->filled('keywords')) {
            $keywords = array_filter(
                array_map('trim', explode(',', $request->keywords))
            );

            if (!empty($keywords)) {
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('name', 'ilike', "%{$keyword}%")
                          ->orWhere('description', 'ilike', "%{$keyword}%");
                    }
                });
            }
        }

        $query->orderBy('created_at', 'desc');

        $perPage  = min((int) $request->get('per_page', 20), 50);
        $paginate = $query->paginate($perPage);

        return response()->json([
            'data'         => $this->formatCollection($paginate->items()),
            'current_page' => $paginate->currentPage(),
            'last_page'    => $paginate->lastPage(),
        ]);
    }


    public function show(string $id): JsonResponse
    {
        // findOrFail triggers ModelNotFoundException → caught globally → 404 JSON
        $product = Product::findOrFail($id);

        return response()->json([
            'data' => $this->formatProduct($product),
        ]);
    }
    
    private function formatProduct(Product $product): array
    {
        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'description' => $product->description,
            'price'       => (float) $product->price,   // Flutter reads as num → toDouble()
            'stock'       => (int)   $product->stock,   // Flutter reads as int
            'category'    => $product->category,        // ENUM string: "peripherals" etc.
            'image_url'   => $product->image_url,
        ];
    }

    private function formatCollection(array $products): array
    {
        return array_map(
            fn(Product $p) => $this->formatProduct($p),
            $products
        );
    }
}