<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'         => 'sometimes|string|max:255',
            'department_id' => 'sometimes|exists:departments,id',
            'description'   => 'nullable|string',
            'status'        => 'nullable|in:active,inactive',
        ];
    }
}
