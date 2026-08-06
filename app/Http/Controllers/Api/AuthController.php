<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and return access token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $credentials = $request->only('email', 'password');

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Unauthorized',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Unauthorized',
                'errors' => [
                    'email' => ['Account is inactive.'],
                ],
            ], 403);
        }

        // Check rate limiting
        if ($this->hasTooManyLoginAttempts($request, $user->email)) {
            return response()->json([
                'message' => 'Too many attempts',
                'errors' => [
                    'email' => ['Too many login attempts. Please try again later.'],
                ],
            ], 429);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Log successful login
        activity()
            ->by($user)
            ->withProperties(['ip' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('Logged in via API');

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Log logout
            activity()
                ->by($user)
                ->withProperties(['ip' => $request->ip()])
                ->log('Logged out via API');

            // Revoke the current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Successfully logged out',
            ]);
        }

        return response()->json([
            'message' => 'No authenticated user',
        ], 401);
    }

    /**
     * Get current user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Check if user has too many login attempts (simple implementation)
     */
    protected function hasTooManyLoginAttempts(Request $request, string $email): bool
    {
        // Use Laravel's rate limiter
        $key = 'login_attempts:' . $email . ':' . $request->ip();

        // Allow 5 attempts per minute
        if (app('rate_limiter')->tooManyAttempts($key, 5)) {
            return true;
        }

        return false;
    }
}
