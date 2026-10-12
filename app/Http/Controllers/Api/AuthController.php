<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue API token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:username', 'string'],
            'username' => ['required_without:email', 'string'],
            'password' => ['required', 'string'],
            'device_token' => ['nullable', 'string'],
        ]);

        $loginIdentifier = $validated['email'] ?? $validated['username'] ?? '';

        $user = User::where('email', $loginIdentifier)
            ->orWhere('username', $loginIdentifier)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status && $user->status !== 'active') {
            return response()->json([
                'message' => 'Account is inactive. Please contact your administrator.',
            ], 403);
        }

        $user->last_login_at = now();

        if (! empty($validated['device_token'])) {
            $user->last_device_token = $validated['device_token'];
        }

        $user->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Register a new user and issue API token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'fullname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $username = $validated['username'] ?? strstr($validated['email'], '@', true);

        // Ensure unique fallback username if duplicate
        if ($username && User::where('username', $username)->exists()) {
            $username = $username.'_'.time();
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'fullname' => $validated['fullname'] ?? $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => 'active',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Return authenticated user profile.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Revoke current user's access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Handle password reset request.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        return response()->json([
            'message' => 'If your email is registered, password reset instructions have been sent.',
        ]);
    }
}
