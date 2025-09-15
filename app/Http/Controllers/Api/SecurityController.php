<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\SecurityLog;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SecurityController extends BaseController
{
    /**
     * Get security dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_security_events' => SecurityLog::count(),
            'unresolved_events' => SecurityLog::unresolved()->count(),
            'high_severity_events' => SecurityLog::highSeverity()->unresolved()->count(),
            'events_today' => SecurityLog::whereDate('created_at', today())->count(),
            'failed_logins_today' => SecurityLog::ofType('failed_login')->whereDate('created_at', today())->count(),
            'suspicious_activities' => SecurityLog::whereIn('type', [
                'sql_injection_attempt',
                'rate_limit_exceeded',
                'suspicious_user_agent'
            ])->unresolved()->count(),
        ];

        // Recent security events
        $recentEvents = SecurityLog::with(['user', 'resolvedBy'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => $log->type_label,
                    'severity' => $log->severity,
                    'severity_color' => $log->severity_color,
                    'ip_address' => $log->ip_address,
                    'user' => $log->user ? $log->user->name : null,
                    'created_at' => $log->created_at,
                    'is_resolved' => $log->isResolved(),
                ];
            });

        // Security trends (last 7 days)
        $trends = SecurityLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                'severity'
            )
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date', 'severity')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Top threat IPs
        $topThreatIps = SecurityLog::select('ip_address', DB::raw('COUNT(*) as count'))
            ->whereIn('severity', ['medium', 'high'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return $this->sendResponse([
            'stats' => $stats,
            'recent_events' => $recentEvents,
            'trends' => $trends,
            'top_threat_ips' => $topThreatIps,
        ], 'Security dashboard data retrieved successfully');
    }

    /**
     * Get security logs with filtering and pagination
     */
    public function logs(Request $request)
    {
        $query = SecurityLog::with(['user', 'resolvedBy']);

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        if ($request->has('resolved')) {
            if ($request->resolved === 'true') {
                $query->resolved();
            } else {
                $query->unresolved();
            }
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $logs = $query->paginate($perPage);

        return $this->sendResponse($logs, 'Security logs retrieved successfully');
    }

    /**
     * Resolve a security log
     */
    public function resolve(Request $request, SecurityLog $securityLog)
    {
        $request->validate([
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $securityLog->markAsResolved(auth()->id());

        if ($request->has('resolution_notes')) {
            $details = $securityLog->details ?? [];
            $details['resolution_notes'] = $request->resolution_notes;
            $securityLog->update(['details' => $details]);
        }

        return $this->sendResponse($securityLog, 'Security log resolved successfully');
    }

    /**
     * Bulk resolve security logs
     */
    public function bulkResolve(Request $request)
    {
        $request->validate([
            'log_ids' => 'required|array',
            'log_ids.*' => 'exists:security_logs,id',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $logs = SecurityLog::whereIn('id', $request->log_ids)->get();
        
        foreach ($logs as $log) {
            $log->markAsResolved(auth()->id());
            
            if ($request->has('resolution_notes')) {
                $details = $log->details ?? [];
                $details['resolution_notes'] = $request->resolution_notes;
                $log->update(['details' => $details]);
            }
        }

        return $this->sendResponse([], "Resolved {$logs->count()} security logs successfully");
    }

    /**
     * Get audit trail logs
     */
    public function auditTrail(Request $request)
    {
        $query = AuditTrail::with('user');

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('event')) {
            $query->where('event', $request->event);
        }

        if ($request->has('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                  ->orWhere('auditable_type', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $auditTrail = $query->paginate($perPage);

        return $this->sendResponse($auditTrail, 'Audit trail retrieved successfully');
    }

    /**
     * Get session management data
     */
    public function sessions()
    {
        $activeSessions = DB::table('personal_access_tokens')
            ->join('users', 'personal_access_tokens.tokenable_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name',
                'users.email',
                'personal_access_tokens.name as token_name',
                'personal_access_tokens.last_used_at',
                'personal_access_tokens.created_at'
            )
            ->where('personal_access_tokens.tokenable_type', 'App\\Models\\User')
            ->orderBy('personal_access_tokens.last_used_at', 'desc')
            ->get();

        $sessionStats = [
            'total_active_sessions' => $activeSessions->count(),
            'unique_users' => $activeSessions->unique('user_id')->count(),
            'sessions_today' => $activeSessions->where('created_at', '>=', today())->count(),
        ];

        return $this->sendResponse([
            'stats' => $sessionStats,
            'sessions' => $activeSessions,
        ], 'Session data retrieved successfully');
    }

    /**
     * Revoke user sessions
     */
    public function revokeSessions(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $revokedCount = 0;
        
        foreach ($request->user_ids as $userId) {
            $user = User::find($userId);
            if ($user) {
                $user->tokens()->delete();
                $revokedCount++;
            }
        }

        return $this->sendResponse([], "Revoked sessions for {$revokedCount} users successfully");
    }
}
