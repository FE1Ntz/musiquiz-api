<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Auth', 'Аутентифікація через Sanctum токени.', weight: 0)]
class AuthController extends Controller
{
    /**
     * Login
     *
     * Authenticate a user and return a Sanctum API token.
     *
     * @operationId login
     *
     * @response 200 {
     *   "token": "1|abc123...",
     *   "user": {"id": 1, "name": "John Doe", "email": "john@example.com"}
     * }
     * @response 422 {"message": "The provided credentials are incorrect.", "errors": {"email": ["The provided credentials are incorrect."]}}
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->validated('device_name'));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current access token.
     *
     * @operationId logout
     *
     * @response 200 {"message": "Logged out successfully."}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
