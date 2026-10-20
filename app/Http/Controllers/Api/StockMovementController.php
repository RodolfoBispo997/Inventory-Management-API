<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return StockMovementResource::collection(
            $product->movements()->orderByDesc('id')->paginate(15)
        );
    }

    public function store(
        StoreStockMovementRequest $request,
        Product $product,
        StockMovementService $service,
    ): JsonResponse {
        $movement = $service->record($product, $request->validated());

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }
}
