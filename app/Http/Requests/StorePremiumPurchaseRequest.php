<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePremiumPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'benefactor_id' => ['nullable', 'exists:benefactors,id'],
            'funding_type_id' => ['nullable', 'exists:funding_types,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'payer_type' => ['required', 'in:individual,employer,government,donor,group,institution'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:40'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'payer_details' => ['nullable', 'array'],
            'payment_method' => ['required', 'in:cash,bank_transfer,pos,online_payment,payroll_deduction,government_subsidy,donor_sponsorship'],
            'payment_status' => ['nullable', 'in:pending,paid,confirmed,cancelled'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'initialize_checkout' => ['nullable', 'boolean'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
