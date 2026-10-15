<?php

namespace App\Http\Middleware;

use App\Models\SystemApiKey;
use Closure;
use Illuminate\Http\Request;

class AuthenticateIntegrationKey
{
    public function handle(Request $request, Closure $next, string $ability)
    {
        $plain = $request->header('X-API-Key') ?: preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $key = $plain ? SystemApiKey::with(['system', 'creator'])->where('key_hash', hash('sha256', $plain))->first() : null;
        if (! $key || $key->revoked_at || ! $key->system || $key->system->status !== 'active' || ! in_array($ability, $key->abilities ?? [], true)) {
            return response()->json(['message' => 'Invalid, revoked, or unauthorized API key.'], 401);
        }
        $key->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('integration_api_key', $key);
        $request->attributes->set('integration_user', $key->creator);
        return $next($request);
    }
}
