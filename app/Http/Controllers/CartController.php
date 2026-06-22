<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function validateCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $requested = collect($validated['items'])->keyBy('product_id');
        $products = Product::whereIn('id', $requested->keys())->get()->keyBy('id');
        $items = [];
        $removed = [];
        $messages = [];
        $subtotal = 0;

        foreach ($requested as $productId => $item) {
            $product = $products->get($productId);
            if (!$product) {
                $removed[] = $productId;
                $messages[] = "Product {$productId} is no longer available.";
                continue;
            }

            $quantity = min((int) $item['quantity'], (int) $product->stock);
            if ($quantity < (int) $item['quantity']) {
                $messages[] = "{$product->name} quantity was adjusted to available stock.";
            }
            if ($quantity === 0) {
                $removed[] = $productId;
                continue;
            }

            $price = (int) round((float) $product->price);
            $lineTotal = $price * $quantity;
            $subtotal += $lineTotal;
            $items[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'quantity' => $quantity,
                'available_stock' => (int) $product->stock,
                'unit_price' => $price,
                'line_total' => $lineTotal,
            ];
        }

        return response()->json([
            'items' => $items,
            'removed_product_ids' => $removed,
            'subtotal' => $subtotal,
            'messages' => $messages,
        ]);
    }
}
