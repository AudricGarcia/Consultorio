<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->user()?->isOwner()) {
                $validator->errors()->add('role_ids', 'No tienes permiso para asignar roles.');
            }

            if ($this->input('role_ids') && Role::whereIn('id', $this->input('role_ids'))->where('name', 'owner')->exists()) {
                $validator->errors()->add('role_ids', 'El rol propietario solo se asigna desde la consola segura.');
            }
        }];
    }
}
