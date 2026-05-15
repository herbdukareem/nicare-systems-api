<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CheckPermission
 *
 * Middleware to ensure the authenticated user has a given permission.
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    /**
     * Accept one or more permission names (comma-separated via middleware() OR variadic).
     * User must have at least one of the listed permissions (OR logic).
     * Super-admin / admin users bypass all permission checks automatically.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Flatten comma-separated permission strings (middleware('permission:a,b,c'))
        $required = [];
        foreach ($permissions as $perm) {
            foreach (explode(',', $perm) as $p) {
                $required[] = trim($p);
            }
        }

        foreach ($required as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden: you do not have the required permission.',
        ], 403);
    }
}