<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'treatment_plan_id' => ['nullable', 'integer', Rule::exists('treatment_plans', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer', 'yape', 'plin', 'other'])],
            'reference' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('payments', 'reference')->where(fn ($query) => $query->where('payment_method', $this->input('payment_method'))),
            ],
        ];
    }
}
