<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'username' => ['nullable', 'string', 'unique:users,username', 'max:255'],
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'same:password'],
            'status' => ['required', 'integer', 'in:0,1,2'], // 0=pending, 1=active, 2=suspended
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'userable_type' => ['nullable', 'string'],
            'userable_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The full name is required.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'username.unique' => 'This username is already taken.',
            'password.required' => 'A password is required.',
            'password_confirmation.same' => 'Password confirmation does not match.',
            'status.required' => 'User status is required.',
            'status.in' => 'Invalid user status selected.',
            'roles.*.exists' => 'One or more selected roles do not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
            'username' => 'username',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'status' => 'status',
            'roles' => 'roles',
        ];
    }
}
