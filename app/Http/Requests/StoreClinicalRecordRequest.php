<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\DentalFdi;

class StoreClinicalRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('clinical.create') ?? false;
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
            'encountered_at' => ['required', 'date', 'before_or_equal:now'],
            'record_type' => ['required', Rule::in(['encounter', 'note', 'prescription', 'consent', 'follow_up'])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'anamnesis' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:10000'],
            'diagnoses' => ['nullable', 'string', 'max:10000'],
            'structured_diagnoses' => ['nullable', 'array', 'max:20'],
            'structured_diagnoses.*.diagnosis' => ['required', 'string', 'max:160'],
            'structured_diagnoses.*.tooth_code' => ['nullable', Rule::in(DentalFdi::toothCodes())],
            'structured_diagnoses.*.certainty' => ['required', Rule::in(['confirmed', 'suspected', 'differential'])],
            'structured_diagnoses.*.notes' => ['nullable', 'string', 'max:1000'],
            'plan' => ['nullable', 'string', 'max:10000'],
            'evolution' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
