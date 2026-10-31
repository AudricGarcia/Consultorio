<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('treatments.catalog.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $catalog = $this->route('treatment_catalog');

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('treatment_catalog', 'code')->ignore($catalog?->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
