<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return SupplierResource::collection(
            $request->user()->organization->suppliers()->orderBy('id')->paginate(15)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $request->user()->organization->suppliers()->create($request->validated());

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    #[PathParameter('supplier', type: 'int')]
    #[Response(status: 404, description: 'Supplier not found in the authenticated organization.')]
    public function show(Request $request, string $supplier): SupplierResource
    {
        return new SupplierResource($request->user()->organization->suppliers()->findOrFail($supplier));
    }
}
