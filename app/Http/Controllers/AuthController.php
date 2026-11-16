<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** bcrypt (cost 12) of a throwaway string: checked when the email is unknown so both paths take equally long. */
    private const TIMING_EQUALIZER_HASH = '$2y$12$9Xi.NdQlDOi5s1/EQ9FoN.Sr5IUlmem370O/LKH7tEqPfokDFbRDa';

    /**
     * Register a new account and return an API token.
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->tokenResponse($user, 201);
    }

    /**
     * Log in with email + password and return an API token.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->lower()->value())->first();

        $passwordMatches = Hash::check($request->string('password')->value(), $user?->password ?? self::TIMING_EQUALIZER_HASH);

        if (! $user || ! $passwordMatches) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        return $this->tokenResponse($user, 200);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, int $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => UserResource::make($user),
                'token' => $user->createToken('api')->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ], $status);
    }
}
