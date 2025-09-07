<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => 'required|string|max:255',
            'department_id'=> 'nullable|exists:departments,id',
            'description'  => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
        ];
    }
}
