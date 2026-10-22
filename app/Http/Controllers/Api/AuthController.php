<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    #[Response(status: 201, type: 'array{data: array{user: array{id: int, name: string, email: string, created_at: string}, token: string}}')]
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = DB::transaction(function () use ($request, $validated): User {
            $organization = Organization::query()->create([
                'name' => $validated['organization_name'],
            ]);

            return $organization->users()->create($request->safe()->only(['name', 'email', 'password']));
        });
        /** @var string $token */
        $token = $user->createToken('inventory-api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'token' => $token,
            ],
        ], 201);
    }

    #[Response(status: 200, type: 'array{data: array{user: array{id: int, name: string, email: string, created_at: string}, token: string}}')]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        /** @var string $token */
        $token = $user->createToken('inventory-api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
