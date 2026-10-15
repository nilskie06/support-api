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
            'user' => $user->load(['assignments', 'menuAccess']),
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
        $user = $request->user();
        if ($user) {
            $user->load(['assignments', 'menuAccess']);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    /** Update the authenticated user's account details. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username,'.$user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->fill([
            'fullname' => $validated['fullname'],
            'name' => $validated['fullname'],
            'username' => $validated['username'] ?: null,
            'email' => $validated['email'],
        ])->save();

        return response()->json(['data' => $user->fresh(['assignments', 'menuAccess'])]);
    }

    /** Verify the active user's password before unlocking the application. */
    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }

        return response()->json(['message' => 'Screen unlocked.']);
    }

    /**
     * Return the authenticated user's UI customizer preferences.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'theme_config' => $user->theme_config,
                'layout_config' => $user->layout_config,
            ],
        ]);
    }

    /**
     * Update the authenticated user's UI customizer preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_config' => ['nullable', 'array'],
            'layout_config' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        if ($request->has('theme_config')) {
            $user->theme_config = $validated['theme_config'];
        }

        if ($request->has('layout_config')) {
            $user->layout_config = $validated['layout_config'];
        }

        $user->save();

        return response()->json([
            'message' => 'User preferences updated successfully',
            'data' => [
                'theme_config' => $user->theme_config,
                'layout_config' => $user->layout_config,
            ],
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
