<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return SupplierResource::collection(Supplier::query()->orderBy('id')->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create($request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }
}
