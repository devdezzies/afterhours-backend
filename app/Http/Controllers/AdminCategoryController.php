<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(['id', 'name', 'is_default']),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ]);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->is_default) {
            return response()->json([
                'message' => 'Default category cannot be renamed.',
            ], 409);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $defaultCategory = $this->defaultCategory();

        if ($category->is_default || $category->id === $defaultCategory->id) {
            return response()->json([
                'message' => 'Default category cannot be deleted.',
            ], 409);
        }

        DB::transaction(function () use ($category, $defaultCategory) {
            Product::where('category_id', $category->id)->update([
                'category_id' => $defaultCategory->id,
            ]);

            $category->delete();
        });

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    private function defaultCategory(): Category
    {
        $category = Category::firstOrCreate([
            'name' => Category::DEFAULT_NAME,
        ]);

        if (! $category->is_default) {
            $category->forceFill(['is_default' => true])->save();
        }

        return $category;
    }
}
