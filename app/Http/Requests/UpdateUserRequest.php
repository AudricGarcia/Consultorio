<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->user()?->isOwner()) {
                $validator->errors()->add('role_ids', 'No tienes permiso para asignar roles.');
            }

            if ($this->input('role_ids') && Role::whereIn('id', $this->input('role_ids'))->where('name', 'owner')->exists()) {
                $validator->errors()->add('role_ids', 'El rol propietario no se puede asignar desde este formulario.');
            }

            if ($this->route('user')?->is(auth()->user()) && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'No puedes desactivar tu propia cuenta.');
            }
        }];
    }
}
