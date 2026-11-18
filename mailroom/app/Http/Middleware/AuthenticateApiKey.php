<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Bearer API keys. A key acts as its user, so the user's role limits what it can do. */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = ApiKey::findByToken((string) $request->bearerToken());
        if ($key === null) {
            return response()->json(['message' => 'Invalid or missing API key.'], 401);
        }
        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }
        Auth::setUser($key->user);

        return $next($request);
    }
}
