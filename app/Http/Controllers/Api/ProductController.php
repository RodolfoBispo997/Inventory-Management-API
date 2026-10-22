<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $request->user()->organization->products()->orderBy('id')->paginate(15)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $request->user()->organization->products()->create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    #[PathParameter('product', type: 'int')]
    #[Response(status: 404, description: 'Product not found in the authenticated organization.')]
    public function show(Request $request, string $product): ProductResource
    {
        return new ProductResource($request->user()->organization->products()->findOrFail($product));
    }
}
