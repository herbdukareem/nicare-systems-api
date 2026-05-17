<?php

namespace App\Http\Middleware;

use App\Models\Enrollee;
use Closure;
use Illuminate\Http\Request;

class EnsureEnrollee
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('sanctum')->user();

        if (!$user || !($user instanceof Enrollee)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Enrollee access only.',
            ], 401);
        }

        return $next($request);
    }
}
