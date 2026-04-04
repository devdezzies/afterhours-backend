<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;   
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'shipping_address' => ['required', 'string'],
            'shipping_lat' => ['required', 'numeric'],
            'shipping_lng' => ['required', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request) {
            $productIds = collect($request->items)->pluck('product_id');

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $totalAmount = collect($request->items)->sum('quantity');

            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => $request->status,
                'shipping_address' => $request->shipping_address,
                'shipping_lat' => $request->shipping_lat,
                'shipping_lng' => $request->shipping_lng,
                'total_amount' => $totalAmount,
            ]);
            
            foreach ($request->items as $item) {
                $product = $products[$item['product_id']];

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for the product {$product->name}");
                }

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price_at_purchase' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
            }

            return response()->json([
                'message' => 'Order created successfully',
                'order_id' => $order->id,
            ], 201);
        });
    }

    public function index(Request $request):JsonResponse
    {
        $orders = Order::with('orderItems.product')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $order = Order::with('orderItems.product')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $order = Order::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        $order->update($request->only([
            'status'
        ]));

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => $order,
        ]);
    }
}