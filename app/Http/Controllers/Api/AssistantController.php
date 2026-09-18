<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Services\Assistant\NiCareAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private NiCareAssistantService $assistant)
    {
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'context' => ['nullable', 'array'],
            'context.page_title' => ['nullable', 'string', 'max:120'],
            'context.route' => ['nullable', 'string', 'max:180'],
        ]);

        $assistantContext = $this->assistantContext($request, $validated['context'] ?? []);
        $rateKey = $this->rateKey($request);

        if (RateLimiter::tooManyAttempts($rateKey, 12)) {
            Log::warning('assistant_rate_limited', [
                'user_type' => $this->userType($request),
                'user_id' => $request->user()?->getKey(),
                'ip' => $request->ip(),
                'retry_after' => RateLimiter::availableIn($rateKey),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Please wait a moment before asking another assistant question.',
                'retry_after_seconds' => RateLimiter::availableIn($rateKey),
            ], 429);
        }

        RateLimiter::hit($rateKey, 60);

        Log::info('assistant_chat_requested', [
            'user_type' => $this->userType($request),
            'user_id' => $request->user()?->getKey(),
            'route' => data_get($validated, 'context.route'),
            'page_area' => $assistantContext['current_page_area'],
            'allowed_areas' => $assistantContext['allowed_areas'],
            'message_length' => strlen($validated['message']),
        ]);

        try {
            $answer = $this->assistant->answer(
                $validated['message'],
                $assistantContext
            );
        } catch (Throwable $exception) {
            Log::error('assistant_chat_failed', [
                'user_type' => $this->userType($request),
                'user_id' => $request->user()?->getKey(),
                'route' => data_get($validated, 'context.route'),
                'page_area' => $assistantContext['current_page_area'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The AI assistant is unavailable right now. Please try again shortly.',
            ], 503);
        }

        Log::info('assistant_chat_completed', [
            'user_type' => $this->userType($request),
            'user_id' => $request->user()?->getKey(),
            'route' => data_get($validated, 'context.route'),
            'page_area' => $assistantContext['current_page_area'],
            'answer_length' => strlen($answer),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'answer' => $answer,
            ],
        ]);
    }

    private function rateKey(Request $request): string
    {
        $user = $request->user();

        return 'assistant-chat:' . ($user ? $this->userType($request) . ':' . $user->getKey() : $request->ip());
    }

    private function userType(Request $request): string
    {
        $user = $request->user();

        return $user ? class_basename($user::class) : 'guest';
    }

    private function assistantContext(Request $request, array $clientContext): array
    {
        $route = (string) ($clientContext['route'] ?? '');
        $pageTitle = (string) ($clientContext['page_title'] ?? '');
        $pageArea = $this->detectPageArea($route, $pageTitle);
        $allowedAreas = $this->allowedHelpAreas($request->user());
        $requiredArea = $this->requiredAreaForPage($pageArea);

        return [
            'route' => $route,
            'page_title' => $pageTitle,
            'current_page_area' => $pageArea,
            'current_page_allowed' => $requiredArea === 'general' || in_array($requiredArea, $allowedAreas, true),
            'allowed_areas' => $allowedAreas,
            'facts' => $this->safeFacts($request->user(), $allowedAreas),
        ];
    }

    private function safeFacts(mixed $user, array $allowedAreas): array
    {
        $facts = [
            'current_user' => $this->currentUserFacts($user),
        ];

        if (array_intersect($allowedAreas, ['dashboard', 'enrollment'])) {
            $facts['dashboard'] = $this->dashboardFacts();
        }

        return array_filter($facts);
    }

    private function currentUserFacts(mixed $user): ?array
    {
        if (!$user || class_basename($user::class) !== 'User') {
            return null;
        }

        $user->loadMissing(['currentRole:id,name,label']);

        return [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'current_role' => $user->currentRole?->label ?: $user->currentRole?->name,
        ];
    }

    private function dashboardFacts(): array
    {
        $today = now()->toDateString();
        $total = Enrollee::count();
        $activeCoverage = $this->activeCoverageQuery($today)->count();
        $pendingApproval = Enrollee::where('status', Enrollee::STATUS_PENDING)->count();
        $coverageRate = $total > 0 ? round(($activeCoverage / $total) * 100, 1) : 0.0;

        return [
            'total_enrollees' => $total,
            'active_coverage' => $activeCoverage,
            'pending_approval' => $pendingApproval,
            'coverage_rate_percent' => $coverageRate,
            'active_programme_mix' => $this->activeProgrammeMix($today, max($activeCoverage, 1)),
            'as_of' => now()->toDateTimeString(),
        ];
    }

    private function activeCoverageQuery(string $date): Builder
    {
        return Enrollee::query()
            ->where('enrollees.status', Enrollee::STATUS_ACTIVE)
            ->whereNotNull('enrollees.coverage_start_date')
            ->where('enrollees.coverage_start_date', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('enrollees.coverage_end_date')
                    ->orWhere('enrollees.coverage_end_date', '>=', $date);
            });
    }

    private function activeProgrammeMix(string $date, int $total): array
    {
        return $this->activeCoverageQuery($date)
            ->leftJoin('insurance_programmes', 'enrollees.insurance_programme_id', '=', 'insurance_programmes.id')
            ->select('insurance_programmes.name as label', DB::raw('COUNT(enrollees.id) as total'))
            ->groupBy('insurance_programmes.id', 'insurance_programmes.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label ?: 'Not Specified',
                'count' => (int) $row->total,
                'percentage' => round(((int) $row->total / $total) * 100, 1),
            ])
            ->all();
    }

    private function detectPageArea(string $route, string $pageTitle): string
    {
        $haystack = strtolower($route . ' ' . $pageTitle);

        return match (true) {
            str_starts_with(strtolower($route), '/enroll') => 'enrollee_portal',
            str_contains($haystack, 'pending-approval'),
            str_contains($haystack, 'approval-review'),
            str_contains($haystack, 'enrollment-approval'),
            str_contains($haystack, 'approve enrollee') => 'enrollment_approval',
            str_contains($haystack, 'nin') => 'nin_verification',
            str_contains($haystack, 'enrollee'),
            str_contains($haystack, 'enrollment') => 'enrollment',
            str_contains($haystack, 'capitation') => 'capitation',
            str_contains($haystack, 'claim') => 'claims',
            str_contains($haystack, 'referral'),
            str_contains($haystack, 'fupa'),
            str_contains($haystack, 'utn'),
            str_contains($haystack, 'pas') => 'pas',
            str_contains($haystack, 'facility') => 'facilities',
            str_contains($haystack, 'premium'),
            str_contains($haystack, 'pin'),
            str_contains($haystack, 'payment') => 'premium',
            str_contains($haystack, 'report'),
            str_contains($haystack, 'analytics') => 'reports',
            str_contains($haystack, 'security'),
            str_contains($haystack, 'audit') => 'security',
            str_contains($haystack, 'setup'),
            str_contains($haystack, 'settings'),
            str_contains($haystack, 'users'),
            str_contains($haystack, 'roles') => 'settings',
            str_contains($haystack, 'dashboard') => 'dashboard',
            default => 'general',
        };
    }

    private function requiredAreaForPage(string $pageArea): string
    {
        return match ($pageArea) {
            'enrollment_approval', 'nin_verification' => 'enrollment',
            default => $pageArea,
        };
    }

    private function allowedHelpAreas(mixed $user): array
    {
        if (!$user) {
            return [];
        }

        if (class_basename($user::class) !== 'User') {
            return ['enrollee_portal'];
        }

        $user->loadMissing([
            'roles.permissions',
            'currentRole.permissions',
            'directPermissions',
        ]);

        $roleNames = collect([$user->currentRole])
            ->filter()
            ->merge($user->roles ?? [])
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name));

        if ($roleNames->contains(fn (string $name) => in_array($name, ['super_admin', 'super admin', 'super-admin'], true))) {
            return ['dashboard', 'enrollment', 'premium', 'facilities', 'claims', 'pas', 'capitation', 'settings', 'reports', 'security'];
        }

        $permissionNames = $this->permissionNames($user);
        $areas = [];

        $map = [
            'dashboard' => ['dashboard.*', 'claims.dashboard.view'],
            'enrollment' => ['enrollee.*', 'enrollees.*', 'enrollment-phase.*', 'mobile-sync.*'],
            'premium' => ['premium.*', 'coverage.renew', 'payment.collection.*', 'settings.payment-collection.*'],
            'facilities' => ['facilities.*', 'setup.facility.*', 'facilities.assign'],
            'claims' => ['claims.*', 'admissions.*', 'payment_batches.*', 'payments.*'],
            'pas' => ['referrals.*', 'pa_codes.*', 'utn.*', 'documents.*'],
            'capitation' => ['capitation.*'],
            'settings' => ['settings.*', 'users.*', 'roles.*', 'permissions.*', 'setup.*', 'benefactor.*', 'benefactors.*'],
            'reports' => ['reports.*', 'enrollees.export', 'capitation.export'],
            'security' => ['audit.*', 'audit.view'],
        ];

        foreach ($map as $area => $patterns) {
            if ($this->matchesAnyPermission($permissionNames, $patterns)) {
                $areas[] = $area;
            }
        }

        return array_values(array_unique($areas));
    }

    private function permissionNames(mixed $user)
    {
        $permissions = collect();

        if ($user->currentRole) {
            $permissions = $permissions->merge($user->currentRole->permissions ?? []);
        } else {
            $permissions = $permissions->merge(collect($user->roles ?? [])->flatMap(fn ($role) => $role->permissions ?? []));
        }

        $permissions = $permissions->merge($user->directPermissions ?? []);

        return $permissions
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => strtolower((string) $name))
            ->unique()
            ->values();
    }

    private function matchesAnyPermission($permissionNames, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = strtolower($pattern);

            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                if ($permissionNames->contains(fn (string $permission) => str_starts_with($permission, $prefix))) {
                    return true;
                }
                continue;
            }

            if ($permissionNames->contains($pattern)) {
                return true;
            }
        }

        return false;
    }
}
