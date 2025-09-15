<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonationMiddleware
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
        // Check if user is being impersonated
        if (Session::has('impersonated_by')) {
            $originalUserId = Session::get('impersonated_by');
            
            // Add impersonation info to request
            $request->merge([
                'is_impersonated' => true,
                'original_user_id' => $originalUserId
            ]);
            
            // Add header to indicate impersonation
            $request->headers->set('X-Impersonated-By', $originalUserId);
        }

        return $next($request);
    }
}
