<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Resources\DocumentRequirementResource;
use App\Models\DocumentRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentRequirementController extends BaseController
{
    /**
     * Get all document requirements
     */
    public function index(Request $request)
    {
        $query = DocumentRequirement::query();

        // Filter by request type
        if ($request->has('request_type')) {
            $query->forRequestType($request->request_type);
        }

        // Filter by required/optional
        if ($request->has('is_required')) {
            if ($request->boolean('is_required')) {
                $query->required();
            } else {
                $query->optional();
            }
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->boolean('status')) {
                $query->active();
            } else {
                $query->where('status', false);
            }
        } else {
            // Default to active only
            $query->active();
        }

        $requirements = $query->ordered()->get();

        return $this->sendResponse(
            DocumentRequirementResource::collection($requirements),
            'Document requirements retrieved successfully'
        );
    }

    /**
     * Get document requirements for referral requests
     */
    public function forReferral()
    {
        $requirements = DocumentRequirement::getReferralRequirements();

        return $this->sendResponse(
            [
                'required' => DocumentRequirementResource::collection(
                    $requirements->where('is_required', true)
                ),
                'optional' => DocumentRequirementResource::collection(
                    $requirements->where('is_required', false)
                ),
            ],
            'Referral document requirements retrieved successfully'
        );
    }

    /**
     * Get document requirements for PA code requests
     */
    public function forPACode()
    {
        $requirements = DocumentRequirement::getPACodeRequirements();

        return $this->sendResponse(
            [
                'required' => DocumentRequirementResource::collection(
                    $requirements->where('is_required', true)
                ),
                'optional' => DocumentRequirementResource::collection(
                    $requirements->where('is_required', false)
                ),
            ],
            'PA Code document requirements retrieved successfully'
        );
    }

    /**
     * Store a new document requirement
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_type' => 'required|in:referral,pa_code,both',
            'document_type' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'allowed_file_types' => 'nullable|string|max:255',
            'max_file_size_mb' => 'nullable|integer|min:1|max:50',
            'display_order' => 'nullable|integer|min:0',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        // Check for unique combination
        $exists = DocumentRequirement::where('request_type', $request->request_type)
            ->where('document_type', $request->document_type)
            ->exists();


        if ($exists) {
            return $this->sendError(
                'Document type already exists for this request type',
                ['document_type' => ['This document type already exists for the selected request type.']],
                422
            );
        }

        $data = $validator->validated();
        $data['created_by'] = auth()->id();

        $requirement = DocumentRequirement::create($data);

        return $this->sendResponse(
            new DocumentRequirementResource($requirement),
            'Document requirement created successfully',
            201
        );
    }

    /**
     * Get a specific document requirement
     */
    public function show(DocumentRequirement $documentRequirement)
    {
        return $this->sendResponse(
            new DocumentRequirementResource($documentRequirement),
            'Document requirement retrieved successfully'
        );
    }

    /**
     * Update a document requirement
     */
    public function update(Request $request, DocumentRequirement $documentRequirement)
    {
        $validator = Validator::make($request->all(), [
            'request_type' => 'sometimes|in:referral,pa_code,both',
            'document_type' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'allowed_file_types' => 'nullable|string|max:255',
            'max_file_size_mb' => 'nullable|integer|min:1|max:50',
            'display_order' => 'nullable|integer|min:0',
            'status' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        // Check for unique combination if changing request_type or document_type
        if ($request->has('request_type') || $request->has('document_type')) {
            $requestType = $request->request_type ?? $documentRequirement->request_type;
            $documentType = $request->document_type ?? $documentRequirement->document_type;

            $exists = DocumentRequirement::where('request_type', $requestType)
                ->where('document_type', $documentType)
                ->where('id', '!=', $documentRequirement->id)
                ->exists();

            if ($exists) {
                return $this->sendError(
                    'Document type already exists for this request type',
                    ['document_type' => ['This document type already exists for the selected request type.']],
                    422
                );
            }
        }

        $data = $validator->validated();
        $data['updated_by'] = auth()->id();

        $documentRequirement->update($data);

        return $this->sendResponse(
            new DocumentRequirementResource($documentRequirement),
            'Document requirement updated successfully'
        );
    }

    /**
     * Delete a document requirement
     */
    public function destroy(DocumentRequirement $documentRequirement)
    {
        $documentRequirement->delete();

        return $this->sendResponse(null, 'Document requirement deleted successfully');
    }

    /**
     * Toggle status of a document requirement
     */
    public function toggleStatus(DocumentRequirement $documentRequirement)
    {
        $documentRequirement->update([
            'status' => !$documentRequirement->status,
            'updated_by' => auth()->id(),
        ]);

        return $this->sendResponse(
            new DocumentRequirementResource($documentRequirement),
            'Document requirement status toggled successfully'
        );
    }

    /**
     * Reorder document requirements
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:document_requirements,id',
            'items.*.display_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        foreach ($request->items as $item) {
            DocumentRequirement::where('id', $item['id'])->update([
                'display_order' => $item['display_order'],
                'updated_by' => auth()->id(),
            ]);
        }

        return $this->sendResponse(null, 'Document requirements reordered successfully');
    }
}
