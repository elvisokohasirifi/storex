<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandRequest;
use App\Http\Requests\ProductCategoryRequest;
use App\Models\Brand;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;

class ProductClassificationController extends Controller
{
    public function category(ProductCategoryRequest $request): JsonResponse
    {
        $category = ProductCategory::create($request->safe()->only(['shop_id', 'name', 'description']));

        return response()->json(['id' => $category->id, 'name' => $category->name, 'shop_id' => $category->shop_id], 201);
    }

    public function brand(BrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->safe()->only(['shop_id', 'name', 'description']));

        return response()->json(['id' => $brand->id, 'name' => $brand->name, 'shop_id' => $brand->shop_id], 201);
    }
}
