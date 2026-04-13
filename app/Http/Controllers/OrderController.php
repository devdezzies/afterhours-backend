<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const ALLOWED_ORDER_FIELDS = [
        'id',
        'status',
        'total_amount',
        'shipping_address',
        'shipping_lat',
        'shipping_lng',
        'created_at',
        'updated_at',
    ];

    private const DEFAULT_ORDER_FIELDS = [
        'id',
        'status',
        'total_amount',
        'shipping_address',
        'shipping_lat',
        'shipping_lng',
        'created_at',
    ];

    private const ALLOWED_INCLUDES = [
        'items',
        'items.product',
    ];

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $this->buildOrderListResponse($request, $user->id);
    }

    protected function buildOrderListResponse(Request $request, ?string $userId = null): JsonResponse
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'max:255'],
            'fields' => [
                'sometimes',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    $invalidFields = array_diff($this->parseCsv($value), self::ALLOWED_ORDER_FIELDS);

                    if (!empty($invalidFields)) {
                        $fail('Invalid fields: '.implode(', ', $invalidFields));
                    }
                },
            ],
            'include' => [
                'sometimes',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    $invalidIncludes = array_diff($this->parseCsv($value), self::ALLOWED_INCLUDES);

                    if (!empty($invalidIncludes)) {
                        $fail('Invalid include value: '.implode(', ', $invalidIncludes));
                    }
                },
            ],
        ]);

        $requestedFields = $this->parseCsv($request->input('fields'));
        $orderFields = empty($requestedFields) ? self::DEFAULT_ORDER_FIELDS : $requestedFields;

        $includes = $this->parseCsv($request->input('include'));
        $includeItems = in_array('items', $includes, true) || in_array('items.product', $includes, true);
        $includeProduct = in_array('items.product', $includes, true);

        $query = Order::query()
            ->select(array_values(array_unique(array_merge(['id', 'user_id'], $orderFields))))
            ->orderBy('created_at', 'desc');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($request->filled('status')) {
            $statusFilters = $this->parseCsv($request->input('status'));

            if (!empty($statusFilters)) {
                $query->whereIn('status', $statusFilters);
            }
        }

        if ($includeItems) {
            $query->with([
                'orderItems' => function ($itemQuery) use ($includeProduct) {
                    $itemQuery->select([
                        'id',
                        'order_id',
                        'product_id',
                        'quantity',
                        'price_at_purchase',
                    ]);

                    if ($includeProduct) {
                        $itemQuery->with('product:id,name,category,image_url');
                    }
                },
            ]);
        }

        $perPage = (int) $request->get('per_page', $request->get('limit', 10));
        $perPage = min($perPage, 100);

        $paginate = $query->paginate($perPage);

        return response()->json([
            'data' => $this->formatCollection(
                $paginate->items(),
                $orderFields,
                $includeItems,
                $includeProduct
            ),
            'current_page' => $paginate->currentPage(),
            'last_page' => $paginate->lastPage(),
            'per_page' => $paginate->perPage(),
            'total' => $paginate->total(),
        ]);
    }

    private function formatCollection(
        array $orders,
        array $fields,
        bool $includeItems,
        bool $includeProduct
    ): array {
        return array_map(
            fn (Order $order) => $this->formatOrder($order, $fields, $includeItems, $includeProduct),
            $orders
        );
    }

    private function formatOrder(Order $order, array $fields, bool $includeItems, bool $includeProduct): array
    {
        $fieldMap = [
            'id' => $order->id,
            'status' => $order->status,
            'total_amount' => (float) $order->total_amount,
            'shipping_address' => $order->shipping_address,
            'shipping_lat' => $order->shipping_lat !== null ? (float) $order->shipping_lat : null,
            'shipping_lng' => $order->shipping_lng !== null ? (float) $order->shipping_lng : null,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];

        $payload = [];

        foreach ($fields as $field) {
            $payload[$field] = $fieldMap[$field] ?? null;
        }

        if ($includeItems) {
            $payload['items'] = $order->orderItems
                ->map(fn (OrderItem $item) => $this->formatOrderItem($item, $includeProduct))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function formatOrderItem(OrderItem $item, bool $includeProduct): array
    {
        $payload = [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => (int) $item->quantity,
            'price_at_purchase' => (float) $item->price_at_purchase,
            'subtotal' => (float) $item->price_at_purchase * (int) $item->quantity,
        ];

        if ($includeProduct) {
            $payload['product'] = $item->product ? [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'category' => $item->product->category,
                'image_url' => $item->product->image_url,
            ] : null;
        }

        return $payload;
    }

    private function parseCsv(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $segment) => trim($segment),
            explode(',', $value)
        ))));
    }
}
