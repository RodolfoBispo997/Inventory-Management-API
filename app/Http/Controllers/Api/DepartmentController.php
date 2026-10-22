<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return DepartmentResource::collection(
            $request->user()->organization->departments()->orderBy('name')->orderBy('id')->paginate(15)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $request->user()->organization->departments()->create($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    #[PathParameter('department', type: 'int')]
    #[Response(status: 404, description: 'Department not found in the authenticated organization.')]
    public function show(Request $request, string $department): DepartmentResource
    {
        return new DepartmentResource($request->user()->organization->departments()->findOrFail($department));
    }
}