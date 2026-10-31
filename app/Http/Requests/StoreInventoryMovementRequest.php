<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $direction = $this->input('direction');

        return ($direction === 'in' && ($this->user()?->can('inventory.receive') || $this->user()?->can('inventory.adjust')))
            || ($direction === 'out' && ($this->user()?->can('inventory.consume') || $this->user()?->can('inventory.adjust')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'inventory_batch_id' => ['nullable', 'integer', Rule::exists('inventory_batches', 'id')],
            'movement_type' => ['required', Rule::in(['receipt', 'consumption', 'damage', 'expiration', 'adjustment'])],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
