<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return DepartmentResource::collection(Department::query()->orderBy('name')->orderBy('id')->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::query()->create($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department);
    }
}
