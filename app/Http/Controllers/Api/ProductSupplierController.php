<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertProductSupplierRequest;
use App\Http\Resources\ProductSupplierResource;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductSupplierController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return ProductSupplierResource::collection(
            $product->suppliers()
                ->orderByPivot('price')
                ->orderBy('suppliers.id')
                ->paginate(15)
        );
    }

    public function update(
        UpsertProductSupplierRequest $request,
        Product $product,
        Supplier $supplier,
    ): JsonResponse {
        $product->suppliers()->syncWithoutDetaching([
            $supplier->id => $request->validated(),
        ]);

        $supplierWithPrice = $product->suppliers()->whereKey($supplier->id)->firstOrFail();

        return (new ProductSupplierResource($supplierWithPrice))->response();
    }
}
