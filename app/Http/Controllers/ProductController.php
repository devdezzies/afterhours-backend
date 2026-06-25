<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page'      => ['sometimes', 'integer', 'min:1'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:50'],
            'category'  => ['sometimes', 'string', 'max:100', 'exists:categories,name'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'keywords'  => ['sometimes', 'string', 'max:500'],
        ]);

        $query = Product::with('category');

        if ($request->filled('category')) {
            $query->whereHas('category', function ($categoryQuery) use ($request) {
                $categoryQuery->where('name', $request->category);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

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
                        $needle = '%'.mb_strtolower($keyword).'%';
                        $q->orWhereRaw('LOWER(name) LIKE ?', [$needle])
                          ->orWhereRaw('LOWER(description) LIKE ?', [$needle]);
                    }
                });
            }
        }

        $query->orderBy('created_at', 'desc');

        $perPage  = min((int) $request->input('per_page', 20), 50);
        $paginate = $query->paginate($perPage);

        return response()->json([
            'data'         => $this->formatCollection($paginate->items()),
            'current_page' => $paginate->currentPage(),
            'last_page'    => $paginate->lastPage(),
            'per_page'     => $paginate->perPage(),
            'total'        => $paginate->total(),
        ]);
    }


    public function show(string $id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);

        return response()->json([
            'data' => $this->formatProduct($product),
        ]);
    }
    
    protected function formatProduct(Product $product): array
    {
        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'description' => $product->description,
            'price'       => (int) round((float) $product->price),
            'stock'       => (int)   $product->stock,   
            'category_id'  => $product->category_id,
            'category'    => $product->category?->name,
            'image_url'   => $product->image_url,
        ];
    }

    protected function formatCollection(array $products): array
    {
        return array_map(
            fn(Product $p) => $this->formatProduct($p),
            $products
        );
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price'       => ['required', 'integer', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id'  => ['required_without:category', 'nullable', 'integer', 'exists:categories,id'],
            'category'     => ['required_without:category_id', 'nullable', 'string', 'max:100', 'exists:categories,name'],
            'image_url'   => ['required', 'url'],
        ]);

        $data = $request->only([
            'name', 'description', 'price', 'stock', 'image_url'
        ]);
        $data['category_id'] = $this->resolveCategoryId($request);

        $product = Product::create($data)->load('category');

        return response()->json([
            'message' => 'Product created successfully',
            'data'    => $this->formatProduct($product),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'price'       => ['sometimes', 'integer', 'min:0'],
            'stock'       => ['sometimes', 'integer', 'min:0'],
            'category_id'  => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'category'     => ['sometimes', 'nullable', 'string', 'max:100', 'exists:categories,name'],
            'image_url'   => ['nullable', 'url'],
        ]);

        $data = $request->only([
            'name', 'description', 'price', 'stock', 'image_url'
        ]);

        if ($request->has('category_id') || $request->has('category')) {
            $data['category_id'] = $this->resolveCategoryId($request);
        }

        $product->update($data);
        $product->load('category');

        return response()->json([
            'message' => 'Product updated successfully',
            'data'    => $this->formatProduct($product),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Product cannot be deleted because it is referenced by an existing order.',
            ], 409);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    protected function resolveCategoryId(Request $request): ?int
    {
        if ($request->has('category_id') && $request->input('category_id') !== null) {
            return (int) $request->input('category_id');
        }

        if ($request->filled('category')) {
            return Category::where('name', $request->category)->value('id');
        }

        return null;
    }
}
