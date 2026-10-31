<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('appointments.manage') ?? false;
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
            'professional_id' => ['required', 'integer', Rule::exists('role_user', 'user_id')->where(fn (Builder $query) => $query->whereIn('role_id', Role::whereIn('name', ['owner', 'dentist'])->select('id')))],
            'starts_at' => ['required', 'date', Rule::when($this->isMethod('POST'), ['after_or_equal:now'])],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['scheduled', 'confirmed', 'cancelled', 'completed', 'no_show'])],
            'administrative_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
