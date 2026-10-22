<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertProductSupplierRequest;
use App\Http\Resources\ProductSupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductSupplierController extends Controller
{
    public function index(Request $request, string $product): AnonymousResourceCollection
    {
        $product = $request->user()->organization->products()->findOrFail($product);

        return ProductSupplierResource::collection(
            $product->suppliers()
                ->orderByPivot('price')
                ->orderBy('suppliers.id')
                ->paginate(15)
        );
    }

    public function update(
        UpsertProductSupplierRequest $request,
        string $product,
        string $supplier,
    ): JsonResponse {
        $organization = $request->user()->organization;
        $product = $organization->products()->findOrFail($product);
        $supplier = $organization->suppliers()->findOrFail($supplier);
        Gate::authorize('manage-inventory-catalog');

        $product->suppliers()->syncWithoutDetaching([
            $supplier->id => [
                ...$request->validated(),
                'organization_id' => $organization->id,
            ],
        ]);

        $supplierWithPrice = $product->suppliers()->whereKey($supplier->id)->firstOrFail();

        return (new ProductSupplierResource($supplierWithPrice))->response();
    }
}
