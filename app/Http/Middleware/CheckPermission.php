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

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'admin'])) {
            return $next($request);
        }

        [$mode, $required] = $this->parsePermissions($permissions);

        if ($required === []) {
            return $next($request);
        }

        $authorized = $mode === 'all'
            ? collect($required)->every(fn (string $permission) => $user->hasPermission($permission))
            : collect($required)->contains(fn (string $permission) => $user->hasPermission($permission));

        if ($authorized) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden: you do not have the required permission.',
            'required_permissions' => $required,
            'match_mode' => $mode,
        ], 403);
    }

    /**
     * Parse middleware arguments.
     *
     * Supported forms:
     * - permission:claims.view
     * - permission:any,claims.view,claims.review
     * - permission:all,claims.view,claims.review
     */
    private function parsePermissions(array $permissions): array
    {
        $parts = [];

        foreach ($permissions as $permissionGroup) {
            foreach (explode(',', $permissionGroup) as $permission) {
                $permission = trim($permission);
                if ($permission !== '') {
                    $parts[] = $permission;
                }
            }
        }

        $mode = 'any';
        if (isset($parts[0]) && in_array(strtolower($parts[0]), ['any', 'all'], true)) {
            $mode = strtolower(array_shift($parts));
        }

        return [$mode, array_values(array_unique($parts))];
    }
}
