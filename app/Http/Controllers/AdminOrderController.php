<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends OrderController
{
    public function index(Request $request): JsonResponse
    {
        return $this->buildOrderListResponse($request);
    }
}
