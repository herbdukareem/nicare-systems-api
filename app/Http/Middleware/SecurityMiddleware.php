<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\SecurityLog;

class SecurityMiddleware
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
        // Check for suspicious activity
        $this->checkSuspiciousActivity($request);
        
        // Rate limiting for sensitive endpoints
        if ($this->isSensitiveEndpoint($request)) {
            $this->applySensitiveRateLimit($request);
        }

        $response = $next($request);

        // Log security events
        $this->logSecurityEvent($request, $response);

        return $response;
    }

    /**
     * Check for suspicious activity patterns
     */
    private function checkSuspiciousActivity(Request $request)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Check for rapid requests from same IP
        $requestKey = "requests:{$ip}";
        $requestCount = Cache::get($requestKey, 0);
        
        if ($requestCount > 100) { // More than 100 requests per minute
            $this->logSuspiciousActivity($request, 'rapid_requests', [
                'request_count' => $requestCount,
                'time_window' => '1_minute'
            ]);
        }
        
        Cache::put($requestKey, $requestCount + 1, 60); // 1 minute TTL

        // Check for suspicious user agents
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $this->logSuspiciousActivity($request, 'suspicious_user_agent', [
                    'user_agent' => $userAgent,
                    'pattern_matched' => $pattern
                ]);
                break;
            }
        }

        // Check for SQL injection attempts in query parameters
        $this->checkSqlInjectionAttempts($request);
    }

    /**
     * Check if the endpoint is sensitive and requires extra protection
     */
    private function isSensitiveEndpoint(Request $request): bool
    {
        $sensitiveEndpoints = [
            'login',
            'password',
            'users',
            'roles',
            'permissions',
            'admin'
        ];

        $path = $request->path();
        
        foreach ($sensitiveEndpoints as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply rate limiting for sensitive endpoints
     */
    private function applySensitiveRateLimit(Request $request)
    {
        $key = 'sensitive:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 30)) { // 30 attempts per minute
            $this->logSuspiciousActivity($request, 'rate_limit_exceeded', [
                'endpoint' => $request->path(),
                'limit' => 30,
                'window' => '1_minute'
            ]);
            
            abort(429, 'Too many requests');
        }
        
        RateLimiter::hit($key, 60); // 1 minute decay
    }

    /**
     * Check for SQL injection attempts
     */
    private function checkSqlInjectionAttempts(Request $request)
    {
        $sqlPatterns = [
            'union.*select',
            'select.*from',
            'insert.*into',
            'delete.*from',
            'update.*set',
            'drop.*table',
            'exec.*sp_',
            'xp_cmdshell',
            '1=1',
            '1\'=\'1',
            'or.*1=1',
            'and.*1=1'
        ];

        $allInput = array_merge(
            $request->query(),
            $request->post(),
            $request->headers->all()
        );

        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match("/{$pattern}/i", $value)) {
                        $this->logSuspiciousActivity($request, 'sql_injection_attempt', [
                            'parameter' => $key,
                            'value' => $value,
                            'pattern_matched' => $pattern
                        ]);
                        
                        // Optionally block the request
                        // abort(400, 'Invalid request');
                        break 2;
                    }
                }
            }
        }
    }

    /**
     * Log security events
     */
    private function logSecurityEvent(Request $request, $response)
    {
        $statusCode = $response->getStatusCode();
        
        // Log failed authentication attempts
        if (str_contains($request->path(), 'login') && $statusCode === 401) {
            $this->logSecurityEvent($request, 'failed_login', [
                'email' => $request->input('email'),
                'status_code' => $statusCode
            ]);
        }

        // Log successful logins
        if (str_contains($request->path(), 'login') && $statusCode === 200) {
            $this->logSecurityEvent($request, 'successful_login', [
                'email' => $request->input('email'),
                'status_code' => $statusCode
            ]);
        }

        // Log access to sensitive endpoints
        if ($this->isSensitiveEndpoint($request) && $statusCode === 200) {
            $this->logSecurityEvent($request, 'sensitive_endpoint_access', [
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'status_code' => $statusCode
            ]);
        }
    }

    /**
     * Log suspicious activity
     */
    private function logSuspiciousActivity(Request $request, string $type, array $details = [])
    {
        SecurityLog::create([
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'details' => $details,
            'severity' => $this->getSeverity($type),
            'created_at' => now(),
        ]);
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(Request $request, string $event, array $data = [])
    {
        SecurityLog::create([
            'type' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'details' => $data,
            'severity' => $this->getSeverity($event),
            'created_at' => now(),
        ]);
    }

    /**
     * Get severity level for different event types
     */
    private function getSeverity(string $type): string
    {
        $highSeverity = [
            'sql_injection_attempt',
            'rate_limit_exceeded',
            'failed_login'
        ];

        $mediumSeverity = [
            'suspicious_user_agent',
            'rapid_requests'
        ];

        if (in_array($type, $highSeverity)) {
            return 'high';
        }

        if (in_array($type, $mediumSeverity)) {
            return 'medium';
        }

        return 'low';
    }
}
