<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $userId, 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'username' => ['sometimes', 'string', 'unique:users,username,' . $userId, 'max:255'],
            'password' => ['sometimes', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required_with:password', 'same:password'],
            'status' => ['sometimes', 'integer', 'in:0,1,2'], // 0=pending, 1=active, 2=suspended
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,id'],
            'userable_type' => ['sometimes', 'string'],
            'userable_id' => ['sometimes', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'The full name must be a valid string.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'username.unique' => 'This username is already taken.',
            'password_confirmation.same' => 'Password confirmation does not match.',
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
