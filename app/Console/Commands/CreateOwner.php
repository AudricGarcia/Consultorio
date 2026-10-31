<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-owner')]
#[Description('Crea la cuenta inicial de doctor propietario de forma interactiva')]
class CreateOwner extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::whereHas('roles', fn ($query) => $query->where('name', 'owner'))->exists()) {
            $this->components->error('Ya existe una cuenta propietaria.');

            return self::FAILURE;
        }

        $name = $this->components->ask('Nombre del propietario');
        $email = $this->components->ask('Correo electrónico');
        $password = $this->secret('Contraseña (mínimo 12 caracteres)');
        $passwordConfirmation = $this->secret('Confirma la contraseña');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $role = Role::where('name', 'owner')->firstOrFail();
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->roles()->attach($role);
        });

        $this->components->info('Cuenta propietaria creada. Ya puedes iniciar sesión.');

        return self::SUCCESS;
    }
}
