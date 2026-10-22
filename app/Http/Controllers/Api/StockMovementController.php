<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Services\StockMovementService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function index(Request $request, string $product): AnonymousResourceCollection
    {
        $product = $request->user()->organization->products()->findOrFail($product);

        return StockMovementResource::collection(
            $product->movements()
                ->with(['department' => fn (BelongsTo $query) => $query->where('departments.organization_id', $product->organization_id)])
                ->orderByDesc('id')
                ->paginate(15)
        );
    }

    public function store(
        StoreStockMovementRequest $request,
        string $product,
        StockMovementService $service,
    ): JsonResponse {
        $product = $request->user()->organization->products()->findOrFail($product);
        $movement = $service->record($product, $request->validated());
        $movement->load(['department' => fn (BelongsTo $query) => $query->where('departments.organization_id', $product->organization_id)]);

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }
}
