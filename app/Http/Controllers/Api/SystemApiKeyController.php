<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\System;
use App\Models\SystemApiKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemApiKeyController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Administrator access is required.');
    }

    public function index(Request $request, System $system): JsonResponse
    {
        $this->authorizeAdmin($request);
        return response()->json(['data' => $system->apiKeys()->latest()->get()->map(fn ($key) => $this->resource($key))]);
    }

    public function store(Request $request, System $system): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'abilities' => ['required', 'array'], 'abilities.*' => ['in:tickets.create,users.enroll,lookups.read']]);
        $plain = 'tsk_'.Str::random(48);
        $key = $system->apiKeys()->create(['created_by' => $request->user()->id, 'name' => $data['name'], 'key_prefix' => substr($plain, 0, 12), 'key_hash' => hash('sha256', $plain), 'abilities' => array_values(array_unique($data['abilities']))]);
        return response()->json(['message' => 'API key created. Copy it now; it cannot be shown again.', 'data' => $this->resource($key), 'api_key' => $plain], 201);
    }

    public function revoke(Request $request, SystemApiKey $apiKey): JsonResponse
    {
        $this->authorizeAdmin($request);
        $apiKey->forceFill(['revoked_at' => now()])->save();
        return response()->json(['message' => 'API key revoked.']);
    }

    public function enroll(Request $request): JsonResponse
    {
        $key = $request->attributes->get('integration_api_key');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'clone_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $user = DB::transaction(function () use ($data, $key) {
            $source = ! empty($data['clone_user_id'])
                ? User::with(['assignments', 'menuAccess'])->findOrFail($data['clone_user_id'])
                : null;

            // New integrations use Standard User defaults; a source user can instead clone its access setup.
            $user = User::create([
                'name' => trim($data['name']),
                'fullname' => trim($data['name']),
                'email' => trim($data['email']),
                'username' => ! empty($data['username']) ? trim($data['username']) : null,
                'password' => Hash::make($data['password']),
                'status' => $source?->status ?: 'active',
                'role' => $source?->role ?: 'users',
            ]);

            if ($source) {
                $user->assignments()->createMany($source->assignments->map(fn ($assignment) => [
                    'reference' => $assignment->reference,
                    'reference_code' => $assignment->reference_code,
                    'default' => (bool) $assignment->default,
                ])->all());

                $user->menuAccess()->createMany($source->menuAccess->map(fn ($access) => [
                    'menu_id' => $access->menu_id,
                    'can_view' => (bool) $access->can_view,
                    'can_add' => (bool) $access->can_add,
                    'can_edit' => (bool) $access->can_edit,
                    'can_delete' => (bool) $access->can_delete,
                ])->all());
            } else {
                app(UserController::class)->syncUserMenuAccessFromRole($user);
            }

            $hasIssuingSystem = $user->assignments()
                ->where('reference', 'system')
                ->where('reference_code', $key->system->code)
                ->exists();

            if (! $hasIssuingSystem) {
                $user->assignments()->create([
                    'reference' => 'system',
                    'reference_code' => $key->system->code,
                    'default' => ! $user->assignments()->where('reference', 'system')->where('default', true)->exists(),
                ]);
            }

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'User enrolled successfully.',
            'data' => $user->load(['assignments', 'menuAccess']),
        ], 201);
    }

    private function resource(SystemApiKey $key): array
    {
        return ['id' => $key->id, 'name' => $key->name, 'prefix' => $key->key_prefix, 'abilities' => $key->abilities, 'last_used_at' => $key->last_used_at, 'revoked_at' => $key->revoked_at, 'created_at' => $key->created_at];
    }
}
