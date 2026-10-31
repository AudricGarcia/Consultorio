<?php

namespace App\Http\Requests;

use App\Support\DentalFdi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treatments.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'session_at' => ['required', 'date', 'before_or_equal:now'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'dental_finding_id' => ['nullable', 'integer', 'exists:odontogram_findings,id'],
            'tooth_code' => ['nullable', 'string', Rule::in(DentalFdi::toothCodes())],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => ['string', Rule::in(DentalFdi::surfaces())],
            'evolution' => ['required', 'string', 'min:3', 'max:10000'],
            'complete_item' => ['sometimes', 'boolean'],
        ];
    }
}
