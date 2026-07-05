<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\EnrollmentFormSchema;
use App\Services\EnrollmentFormSchemaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrollmentFormSchemaController extends BaseController
{
    public function __construct(private EnrollmentFormSchemaService $service)
    {
    }

    public function index(Request $request)
    {
        $schemas = EnrollmentFormSchema::with(['programme:id,name', 'category:id,name', 'plan:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->query('channel')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->sendResponse([
            'schemas' => $schemas,
            'default_fields' => $this->service->defaultFields(),
            'default_nin_verification_policy' => $this->service->defaultNinVerificationPolicy(false),
        ], 'Enrollment form schemas retrieved.');
    }

    public function store(Request $request)
    {
        $schema = $this->service->create($this->validated($request), $request->user()?->id);

        return $this->sendResponse($schema->load(['programme', 'category', 'plan']), 'Enrollment form schema created.', 201);
    }

    public function show(EnrollmentFormSchema $schema)
    {
        return $this->sendResponse($schema->load(['programme', 'category', 'plan']), 'Enrollment form schema retrieved.');
    }

    public function update(Request $request, EnrollmentFormSchema $schema)
    {
        $schema = $this->service->update($schema, $this->validated($request, true), $request->user()?->id);

        return $this->sendResponse($schema, 'Enrollment form schema updated.');
    }

    public function publish(Request $request, EnrollmentFormSchema $schema)
    {
        return $this->sendResponse($this->service->publish($schema, $request->user()?->id), 'Enrollment form schema published.');
    }

    public function revoke(Request $request, EnrollmentFormSchema $schema)
    {
        return $this->sendResponse($this->service->revoke($schema, $request->user()?->id), 'Enrollment form schema revoked.');
    }

    public function destroy(EnrollmentFormSchema $schema)
    {
        $schema->delete();

        return $this->sendResponse([], 'Enrollment form schema archived.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:40'],
            'insurance_programme_id' => ['nullable', 'exists:insurance_programmes,id'],
            'enrollee_category_id' => ['nullable', 'exists:enrollee_categories,id'],
            'premium_plan_id' => ['nullable', 'exists:premium_plans,id'],
            'benefactor_ids' => ['nullable', 'array'],
            'benefactor_ids.*' => ['integer', 'exists:benefactors,id'],
            'version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived', 'revoked'])],
            'requires_nin_verification' => ['nullable', 'boolean'],
            'nin_verification_policy' => ['nullable', 'array'],
            'nin_verification_policy.mode' => ['nullable', Rule::in(['none', 'deferred', 'live_preferred', 'live_required', 'online_only'])],
            'nin_verification_policy.offline_behavior' => ['nullable', Rule::in(['allow_capture', 'defer_until_sync', 'block_capture'])],
            'nin_verification_policy.conflict_status' => ['nullable', Rule::in(['requires_review', 'nin_failed'])],
            'nin_verification_policy.autofill' => ['nullable', 'array'],
            'nin_verification_policy.autofill.enabled' => ['nullable', 'boolean'],
            'nin_verification_policy.autofill.overwrite_strategy' => ['nullable', Rule::in(['empty_only', 'always', 'never'])],
            'nin_verification_policy.autofill.lock_verified_fields' => ['nullable', 'boolean'],
            'nin_verification_policy.autofill.editable_fields' => ['nullable', 'array'],
            'nin_verification_policy.autofill.editable_fields.*' => ['string', 'max:120'],
            'nin_verification_policy.autofill.fields' => ['nullable', 'array'],
            'allow_offline_capture' => ['nullable', 'boolean'],
            'fields' => [$required, 'array'],
            'rules' => ['nullable', 'array'],
            'ui_schema' => ['nullable', 'array'],
            'migration_hints' => ['nullable', 'array'],
        ]);
    }
}
