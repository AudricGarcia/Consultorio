<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'patients.view' => 'Consultar pacientes',
            'patients.create' => 'Registrar pacientes',
            'patients.update' => 'Actualizar datos de pacientes',
            'clinical.view' => 'Consultar historia clínica',
            'clinical.create' => 'Registrar historia clínica',
            'clinical.validate' => 'Validar registros clínicos',
            'odontogram.view' => 'Consultar odontogramas',
            'odontogram.create' => 'Registrar hallazgos odontológicos',
            'appointments.view' => 'Consultar agenda',
            'appointments.manage' => 'Gestionar citas',
            'treatments.manage' => 'Gestionar tratamientos',
            'treatments.catalog.manage' => 'Administrar catálogo de tratamientos',
            'payments.view' => 'Consultar pagos',
            'payments.create' => 'Registrar pagos',
            'patients.payment_lookup' => 'Identificar pacientes para registrar pagos',
            'payments.refund' => 'Autorizar devoluciones',
            'expenses.view' => 'Consultar egresos',
            'expenses.create' => 'Registrar egresos',
            'expenses.approve' => 'Aprobar egresos',
            'cash.register' => 'Operar caja',
            'cash.close' => 'Cerrar caja',
            'cash.reopen' => 'Reabrir caja',
            'inventory.view' => 'Consultar inventario',
            'inventory.receive' => 'Recibir inventario',
            'inventory.consume' => 'Registrar consumo',
            'inventory.adjust' => 'Ajustar inventario',
            'purchases.manage' => 'Gestionar compras',
            'purchases.pay' => 'Registrar pagos a proveedores',
            'reports.financial' => 'Consultar reportes financieros',
            'users.manage' => 'Gestionar usuarios',
            'roles.manage' => 'Gestionar roles y permisos',
            'audit.view' => 'Consultar auditoría',
            'inventory.manage' => 'Administrar catálogo de inventario',
            'suppliers.manage' => 'Gestionar proveedores',
            'purchases.receive' => 'Recibir compras',
            'cash.view' => 'Consultar caja',
            'treatments.view' => 'Consultar tratamientos',
        ];

        $permissionModels = collect($permissions)->mapWithKeys(function (string $label, string $name): array {
            return [$name => Permission::firstOrCreate(['name' => $name], ['label' => $label])];
        });

        $allPermissions = $permissionModels->pluck('id')->all();

        $roles = [
            'owner' => ['Doctor propietario', $allPermissions],
            'dentist' => ['Odontólogo colaborador', ['patients.view', 'clinical.view', 'clinical.create', 'odontogram.view', 'odontogram.create', 'appointments.view', 'appointments.manage', 'treatments.view', 'treatments.manage', 'inventory.view', 'inventory.consume']],
            'assistant' => ['Asistente dental', ['patients.view', 'patients.create', 'patients.update', 'appointments.view', 'appointments.manage', 'inventory.view', 'inventory.consume']],
            'cashier' => ['Asistente administrativo o de caja', ['appointments.view', 'payments.view', 'payments.create', 'patients.payment_lookup', 'expenses.view', 'expenses.create', 'cash.view', 'cash.register', 'cash.close']],
            'inventory' => ['Encargado de inventario', ['inventory.view', 'inventory.manage', 'inventory.receive', 'inventory.consume', 'inventory.adjust', 'suppliers.manage', 'purchases.manage', 'purchases.receive']],
        ];

        foreach ($roles as $name => [$label, $grantedNames]) {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $label]);
            $grantedIds = $name === 'owner'
                ? $allPermissions
                : $permissionModels->only($grantedNames)->pluck('id')->all();
            $role->permissions()->sync($grantedIds);
        }
    }
}
