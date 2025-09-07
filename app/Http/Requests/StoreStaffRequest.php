<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'date_of_birth'  => 'nullable|date',
            'gender'         => 'nullable|in:Male,Female,Other',
            'email'          => 'nullable|email|max:255|unique:staff,email',
            'phone'          => 'nullable|string|max:255',
            'designation_id' => 'nullable|exists:designations,id',
            'department_id'  => 'nullable|exists:departments,id',
            'address'        => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
        ];
    }
}
