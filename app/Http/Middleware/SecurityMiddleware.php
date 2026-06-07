<?php

namespace App\Http\Middleware;

use App\Models\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'remember_token',
        'authorization',
        'nin',
        'medical_history',
        'medication_history',
        'presenting_complains',
        'presenting_complaints',
        'reasons_for_referral',
        'treatments_given',
        'investigations_done',
        'examination_findings',
        'preliminary_diagnosis',
        'diagnosis_update',
        'requested_services',
        'case_record_ids',
        'line_items',
        'bundle_components',
    ];

    private const SENSITIVE_PATHS = [
        'login',
        'password',
        'users',
        'roles',
        'permissions',
        'admin',
        'security',
        'claims',
        'referrals',
        'pa-codes',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $this->inspectRequest($request);

        if ($this->isSensitiveEndpoint($request)) {
            $this->applySensitiveRateLimit($request);
        }

        $response = $next($request);

        $this->recordResponseSecurityEvents($request, $response);

        return $response;
    }

    private function inspectRequest(Request $request): void
    {
        $this->checkSuspiciousActivity($request);
        $this->checkSqlInjectionAttempts($request);
    }

    private function checkSuspiciousActivity(Request $request): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        $requestKey = "requests:{$ip}";
        $requestCount = (int) Cache::get($requestKey, 0);

        if ($requestCount > 100) {
            $this->persistSecurityEvent($request, 'rapid_requests', [
                'request_count' => $requestCount,
                'time_window' => '1_minute',
            ]);
        }

        Cache::put($requestKey, $requestCount + 1, 60);

        foreach (['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget'] as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $this->persistSecurityEvent($request, 'suspicious_user_agent', [
                    'pattern_matched' => $pattern,
                ]);
                break;
            }
        }
    }

    private function isSensitiveEndpoint(Request $request): bool
    {
        $path = strtolower($request->path());

        foreach (self::SENSITIVE_PATHS as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    private function applySensitiveRateLimit(Request $request): void
    {
        $key = 'sensitive:' . sha1($request->ip() . '|' . strtolower($request->path()));

        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->persistSecurityEvent($request, 'rate_limit_exceeded', [
                'endpoint' => $request->path(),
                'limit' => 30,
                'window' => '1_minute',
            ]);

            abort(429, 'Too many requests');
        }

        RateLimiter::hit($key, 60);
    }

    private function checkSqlInjectionAttempts(Request $request): void
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
            'and.*1=1',
        ];

        $input = array_merge($request->query(), $request->post());
        $headerBag = [
            'user-agent' => (string) $request->userAgent(),
            'content-type' => (string) $request->header('Content-Type'),
            'accept' => (string) $request->header('Accept'),
        ];

        foreach (array_merge($input, $headerBag) as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach ($sqlPatterns as $pattern) {
                if (preg_match("/{$pattern}/i", $value)) {
                    $this->persistSecurityEvent($request, 'sql_injection_attempt', [
                        'parameter' => $key,
                        'pattern_matched' => $pattern,
                    ]);

                    return;
                }
            }
        }
    }

    private function recordResponseSecurityEvents(Request $request, Response $response): void
    {
        $statusCode = $response->getStatusCode();
        $path = strtolower($request->path());

        if (str_contains($path, 'login') && $statusCode === 401) {
            $this->persistSecurityEvent($request, 'failed_login', ['status_code' => $statusCode]);
        }

        if (str_contains($path, 'login') && $statusCode === 200) {
            $this->persistSecurityEvent($request, 'successful_login', ['status_code' => $statusCode]);
        }

        if ($this->isSensitiveEndpoint($request) && $statusCode < 400) {
            $this->persistSecurityEvent($request, 'sensitive_endpoint_access', [
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'status_code' => $statusCode,
            ]);
        }
    }

    private function persistSecurityEvent(Request $request, string $event, array $details = []): void
    {
        SecurityLog::create([
            'type' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'details' => $this->sanitize($details),
            'severity' => $this->getSeverity($event),
            'user_id' => $request->user()?->id,
        ]);
    }

    private function sanitize(array $details): array
    {
        $sanitized = [];

        foreach ($details as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($key === $sensitiveKey || str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function getSeverity(string $type): string
    {
        $highSeverity = [
            'sql_injection_attempt',
            'rate_limit_exceeded',
            'failed_login',
        ];

        $mediumSeverity = [
            'suspicious_user_agent',
            'rapid_requests',
        ];

        if (in_array($type, $highSeverity, true)) {
            return 'high';
        }

        if (in_array($type, $mediumSeverity, true)) {
            return 'medium';
        }

        return 'low';
    }
}
