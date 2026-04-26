<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOrderController extends OrderController
{
    public function index(Request $request): JsonResponse
    {
        return $this->buildOrderListResponse($request);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::with([
            'orderItems' => function ($query) {
                $query->select([
                    'id',
                    'order_id',
                    'product_id',
                    'quantity',
                    'price_at_purchase',
                ]);
                $query->with('product:id,name,category,image_url');
            },
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'id'               => $order->id,
                'user_id'          => $order->user_id,
                'status'           => $order->status,
                'total_amount'     => (float) $order->total_amount,
                'shipping_address' => $order->shipping_address,
                'shipping_lat'     => $order->shipping_lat !== null ? (float) $order->shipping_lat : null,
                'shipping_lng'     => $order->shipping_lng !== null ? (float) $order->shipping_lng : null,
                'created_at'       => $order->created_at?->toISOString(),
                'updated_at'       => $order->updated_at?->toISOString(),
                'items'            => $order->orderItems->map(function ($item) {
                    return [
                        'id'                => $item->id,
                        'product_id'        => $item->product_id,
                        'quantity'          => (int) $item->quantity,
                        'price_at_purchase' => (float) $item->price_at_purchase,
                        'subtotal'          => (float) $item->price_at_purchase * (int) $item->quantity,
                        'product'           => $item->product ? [
                            'id'        => $item->product->id,
                            'name'      => $item->product->name,
                            'category'  => $item->product->category,
                            'image_url' => $item->product->image_url,
                        ] : null,
                    ];
                })->values()->all(),
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => ['required', 'string', Rule::in([
                'pending', 'processing', 'shipped', 'delivered', 'cancelled',
            ])],
        ]);

        $order->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Order status updated successfully',
            'data'    => [
                'id'         => $order->id,
                'status'     => $order->status,
                'updated_at' => $order->updated_at?->toISOString(),
            ],
        ]);
    }
}
