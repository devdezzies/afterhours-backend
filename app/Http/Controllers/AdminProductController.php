<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends ProductController
{
    public function index(Request $request): JsonResponse
    {
        return parent::index($request);
    }

    public function show(string $id): JsonResponse
    {
        return parent::show($id);
    }

    public function create(Request $request): JsonResponse
    {
        return parent::create($request);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return parent::update($request, $id);
    }

    public function destroy(string $id): JsonResponse
    {
        return parent::destroy($id);
    }
}