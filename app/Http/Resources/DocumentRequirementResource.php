<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentRequirementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_type' => $this->request_type,
            'document_type' => $this->document_type,
            'name' => $this->name,
            'description' => $this->description,
            'is_required' => $this->is_required,
            'allowed_file_types' => $this->allowed_file_types,
            'allowed_file_types_array' => $this->allowed_file_types_array,
            'max_file_size_mb' => $this->max_file_size_mb,
            'max_file_size_bytes' => $this->max_file_size_bytes,
            'display_order' => $this->display_order,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

