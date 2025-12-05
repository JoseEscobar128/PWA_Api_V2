<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to check if authenticated user has any of the given roles.
 * Usage: ->middleware('role:admin') or 'role:admin,moderator'
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $roles = array_map('trim', explode(',', $roles));

        if (!method_exists($user, 'hasRole')) {
            return response()->json(['success' => false, 'message' => 'Role system not configured'], 500);
        }

        if (!$user->hasRole($roles)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
