<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditTrail;

class AuditMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only audit authenticated requests
        if (Auth::check()) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    /**
     * Log user activity
     */
    private function logActivity(Request $request, $response)
    {
        $user = Auth::user();
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();

        // Skip certain routes to avoid noise
        $skipRoutes = [
            'api/v1/dashboard',
            'api/v1/users/activities',
            'sanctum/csrf-cookie'
        ];

        foreach ($skipRoutes as $skipRoute) {
            if (str_contains($path, $skipRoute)) {
                return;
            }
        }

        // Determine event type based on method and path
        $event = $this->determineEvent($method, $path);
        
        if (!$event) {
            return; // Skip if we can't determine the event
        }

        // Prepare audit data
        $auditData = [
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => $this->getAuditableType($path),
            'auditable_id' => $this->getAuditableId($request, $path),
            'old_values' => $this->getOldValues($request),
            'new_values' => $this->getNewValues($request),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'tags' => $this->getTags($method, $path),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Only log if status code indicates success
        if ($statusCode >= 200 && $statusCode < 300) {
            AuditTrail::create($auditData);
        }
    }

    /**
     * Determine the event type based on HTTP method and path
     */
    private function determineEvent(string $method, string $path): ?string
    {
        // Authentication events
        if (str_contains($path, 'login')) {
            return 'login';
        }
        if (str_contains($path, 'logout')) {
            return 'logout';
        }

        // CRUD events
        switch ($method) {
            case 'POST':
                if (str_contains($path, 'password')) {
                    return 'password_changed';
                }
                if (str_contains($path, 'roles')) {
                    return 'role_assigned';
                }
                if (str_contains($path, 'impersonate')) {
                    return 'impersonation_started';
                }
                return 'created';
            
            case 'PUT':
            case 'PATCH':
                if (str_contains($path, 'toggle-status')) {
                    return 'status_changed';
                }
                if (str_contains($path, 'toggle-2fa')) {
                    return '2fa_toggled';
                }
                return 'updated';
            
            case 'DELETE':
                return 'deleted';
            
            case 'GET':
                if (str_contains($path, 'export')) {
                    return 'exported';
                }
                return null; // Don't log GET requests by default
            
            default:
                return null;
        }
    }

    /**
     * Get the auditable type from the path
     */
    private function getAuditableType(string $path): ?string
    {
        if (str_contains($path, 'users')) {
            return 'App\Models\User';
        }
        if (str_contains($path, 'roles')) {
            return 'App\Models\Role';
        }
        if (str_contains($path, 'permissions')) {
            return 'App\Models\Permission';
        }
        if (str_contains($path, 'enrollees')) {
            return 'App\Models\Enrollee';
        }
        if (str_contains($path, 'facilities')) {
            return 'App\Models\Facility';
        }
        if (str_contains($path, 'benefactors')) {
            return 'App\Models\Benefactor';
        }

        return null;
    }

    /**
     * Extract auditable ID from request path
     */
    private function getAuditableId(Request $request, string $path): ?int
    {
        // Try to get ID from route parameters
        $route = $request->route();
        if ($route) {
            $parameters = $route->parameters();
            
            // Common parameter names for IDs
            $idParams = ['id', 'user', 'role', 'permission', 'enrollee', 'facility', 'benefactor'];
            
            foreach ($idParams as $param) {
                if (isset($parameters[$param])) {
                    $value = $parameters[$param];
                    return is_object($value) ? $value->id : (int) $value;
                }
            }
        }

        return null;
    }

    /**
     * Get old values for update operations
     */
    private function getOldValues(Request $request): ?array
    {
        // For update operations, we'd need to fetch the current state
        // This is a simplified version - in practice, you might want to
        // implement this more thoroughly
        return null;
    }

    /**
     * Get new values from request
     */
    private function getNewValues(Request $request): ?array
    {
        $data = $request->all();
        
        // Remove sensitive data
        unset($data['password'], $data['password_confirmation'], $data['current_password']);
        
        return empty($data) ? null : $data;
    }

    /**
     * Get tags for categorizing audit logs
     */
    private function getTags(string $method, string $path): array
    {
        $tags = [$method];
        
        if (str_contains($path, 'users')) {
            $tags[] = 'user_management';
        }
        if (str_contains($path, 'roles') || str_contains($path, 'permissions')) {
            $tags[] = 'access_control';
        }
        if (str_contains($path, 'enrollees')) {
            $tags[] = 'enrollee_management';
        }
        if (str_contains($path, 'security')) {
            $tags[] = 'security';
        }

        return $tags;
    }
}
