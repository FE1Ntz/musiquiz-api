<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Models\RefreshToken;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Group('Auth', 'Аутентифікація через Sanctum токени.', weight: 0)]
class AuthController extends Controller
{
    /**
     * Access token lifetime in minutes.
     */
    private const int ACCESS_TOKEN_EXPIRATION_MINUTES = 60;

    /**
     * Refresh token lifetime in days.
     */
    private const int REFRESH_TOKEN_EXPIRATION_DAYS = 30;

    /**
     * Login
     *
     * Authenticate a user and return access + refresh tokens.
     *
     * @operationId login
     *
     * @response 200 {
     *   "token": "1|abc123...",
     *   "refresh_token": "def456...",
     *   "expires_in": 3600,
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

        return $this->issueTokenPair($user, $request->validated('device_name'));
    }

    /**
     * Refresh
     *
     * Exchange a valid refresh token for a new access + refresh token pair.
     * The old refresh token is revoked upon use.
     *
     * @operationId refresh
     *
     * @response 200 {
     *   "token": "2|xyz789...",
     *   "refresh_token": "ghi012...",
     *   "expires_in": 3600,
     *   "user": {"id": 1, "name": "John Doe", "email": "john@example.com"}
     * }
     * @response 401 {"message": "Invalid or expired refresh token."}
     *
     * @unauthenticated
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $hashedToken = hash('sha256', $request->validated('refresh_token'));

        $refreshToken = RefreshToken::where('token', $hashedToken)->first();

        if (! $refreshToken || $refreshToken->isExpired()) {
            $refreshToken?->delete();

            return response()->json([
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        $user = $refreshToken->user;
        $deviceName = $refreshToken->device_name;

        // Revoke the used refresh token (rotation)
        $refreshToken->delete();

        return $this->issueTokenPair($user, $deviceName);
    }

    /**
     * Logout
     *
     * Revoke the current access token and optionally the associated refresh token.
     *
     * @operationId logout
     *
     * @response 200 {"message": "Logged out successfully."}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        // If a refresh_token is provided, revoke it too
        if ($request->filled('refresh_token')) {
            $hashedToken = hash('sha256', $request->input('refresh_token'));
            RefreshToken::where('token', $hashedToken)
                ->where('user_id', $request->user()->id)
                ->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Issue a new access + refresh token pair.
     */
    private function issueTokenPair(User $user, string $deviceName): JsonResponse
    {
        // Revoke old refresh tokens for this device
        $user->refreshTokens()
            ->where('device_name', $deviceName)
            ->delete();

        // Create short-lived access token
        $accessToken = $user->createToken(
            $deviceName,
            ['*'],
            now()->addMinutes(self::ACCESS_TOKEN_EXPIRATION_MINUTES),
        );

        // Create long-lived refresh token
        $plainRefreshToken = Str::random(64);

        $user->refreshTokens()->create([
            'token' => hash('sha256', $plainRefreshToken),
            'device_name' => $deviceName,
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_EXPIRATION_DAYS),
        ]);

        return response()->json([
            'token' => $accessToken->plainTextToken,
            'refresh_token' => $plainRefreshToken,
            'expires_in' => self::ACCESS_TOKEN_EXPIRATION_MINUTES * 60,
            'user' => $user,
        ]);
    }
}
