<?php

namespace App\Http\Requests;

use App\Support\DentalFdi;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDentalFindingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('odontogram.create') ?? false;
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
            'clinical_record_id' => ['nullable', 'integer', 'exists:clinical_records,id'],
            'tooth_code' => ['required', 'string', Rule::in(DentalFdi::toothCodes())],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => ['string', Rule::in(DentalFdi::surfaces())],
            'finding_type' => ['required', Rule::in(['caries', 'restoration', 'missing', 'fracture', 'crown', 'prosthesis', 'implant', 'endodontics', 'periodontal', 'other'])],
            'certainty' => ['required', Rule::in(['observed', 'confirmed', 'recommended', 'accepted', 'performed', 'follow_up'])],
            'treatment_status' => ['required', Rule::in(['none', 'recommended', 'accepted', 'performed', 'follow_up'])],
            'description' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
