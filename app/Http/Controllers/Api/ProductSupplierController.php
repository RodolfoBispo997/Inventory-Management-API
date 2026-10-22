<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertProductSupplierRequest;
use App\Http\Resources\ProductSupplierResource;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductSupplierController extends Controller
{
    #[PathParameter('product', type: 'int')]
    #[Response(status: 404, description: 'Product not found in the authenticated organization.')]
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

    #[PathParameter('product', type: 'int')]
    #[PathParameter('supplier', type: 'int')]
    #[Response(status: 404, description: 'Product or supplier not found in the authenticated organization.')]
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