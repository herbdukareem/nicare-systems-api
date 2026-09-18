<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NiCareAssistantService
{
    public function answer(string $question, array $context = []): string
    {
        $key = config('services.openai.key');

        if (!$key) {
            throw new RuntimeException('The AI assistant provider is not configured.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(30)
            ->retry(1, 250)
            ->post('/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4o-mini'),
                'temperature' => 0.2,
                'max_tokens' => 450,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($context['allowed_areas'] ?? [])],
                    ['role' => 'user', 'content' => $this->userPrompt($question, $context)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('The AI assistant provider request failed.');
        }

        $answer = trim((string) data_get($response->json(), 'choices.0.message.content'));

        if ($answer === '') {
            throw new RuntimeException('The AI assistant provider returned an empty response.');
        }

        return $answer;
    }

    private function systemPrompt(array $allowedAreas): string
    {
        $allowedHelp = $this->allowedHelpContent($allowedAreas);

        return <<<PROMPT
You are NiCare A.I., a concise help assistant for the Niger State Contributory Health Agency portal.

Answer only from the safe product-help context listed below for this authenticated user.
You may also answer from the verified portal facts included in the user message. Those facts are already permission-filtered by the backend.
If a question asks about a module, page, record, action or private data outside the allowed context, say that the user may not have access and should contact an authorized admin.

Allowed context:
{$allowedHelp}

Rules:
- Do not claim access to records, databases, personal medical data, passwords, API keys or payment secrets.
- Do not make up exact figures, dates, user permissions or patient/enrollee details. Use exact figures only when they appear in verified portal facts.
- If asked for a specific record or private data, tell the user to open the relevant page or contact an authorized admin.
- Never run, write, suggest, or walk through destructive SQL/database commands, including DELETE, DROP, TRUNCATE, ALTER, UPDATE, INSERT, REPLACE, PURGE, WIPE, RESET, or mass data changes. Redirect the user to safe portal workflows.
- Do not provide medical, legal or financial advice. Keep the answer about using the portal.
- Keep answers short, practical and step-by-step when helpful.
- Use plain text only. Avoid markdown tables.
PROMPT;
    }

    private function userPrompt(string $question, array $context): string
    {
        $pageTitle = (string) ($context['page_title'] ?? 'Unknown page');
        $route = (string) ($context['route'] ?? 'Unknown route');
        $pageArea = (string) ($context['current_page_area'] ?? 'general');
        $pageAllowed = ($context['current_page_allowed'] ?? false) ? 'yes' : 'no';
        $facts = json_encode($context['facts'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "Current page: {$pageTitle}\nRoute: {$route}\nDetected page area: {$pageArea}\nCurrent page area allowed: {$pageAllowed}\nVerified portal facts available to answer from:\n{$facts}\nUser question: {$question}";
    }

    private function allowedHelpContent(array $allowedAreas): string
    {
        $catalog = [
            'dashboard' => 'Dashboard: coverage rate, active coverage, approval rate, programme mix, LGA/geographic reach, facility readiness, financial and capitation summaries.',
            'enrollment' => 'Enrollment: enrollee list, pending approval, approval review, NIN verification, enrollee profile review, bulk enrollment slip, ID card printing, duplicate/integrity tools.',
            'premium' => 'Premium & Enrollment: premium plans, premium purchases, PINs, coverage renewals and payment collection settings.',
            'facilities' => 'Facilities: facility records, accreditation/status, LGA/ward assignment and provider details.',
            'claims' => 'Claims: claim submission, review, validation, approval, rejection and payment workflows.',
            'pas' => 'PAS/referrals: referral tracking, FUPA requests and approvals, UTN validation, document requirements and facility assignment.',
            'capitation' => 'Capitation: generated amount, paid amount, capitation periods, facility capitation lists and payment reports.',
            'settings' => 'Setup/settings: users, roles, permissions, organization settings, NIN provider settings, payment gateway settings and device management.',
            'reports' => 'Reports: dashboards, analytics, filtered exports and payment/capitation/claims reports.',
            'security' => 'Security: audit logs, security dashboard, sessions and audit trail review.',
            'enrollee_portal' => 'Enrollee portal: enrollee dashboard, profile, premium plans, password changes, coverage status and renewals available to the signed-in enrollee.',
        ];

        $lines = collect($allowedAreas)
            ->unique()
            ->map(fn (string $area) => $catalog[$area] ?? null)
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return '- General navigation and account help only. Do not explain restricted operational modules.';
        }

        return $lines->map(fn (string $line) => '- ' . $line)->implode("\n");
    }
}
