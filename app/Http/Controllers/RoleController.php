<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $roles = Role::withCount('users')->with('permissions')->where('name', '!=', 'owner')->orderBy('label')->get();

        return view('roles.index', compact('roles'));
    }

    public function edit(Role $role): View
    {
        abort_unless(auth()->user()->isOwner(), 403);
        abort_if($role->name === 'owner', 403, 'Los permisos del propietario no se pueden reducir desde el panel.');
        $role->load('permissions');
        $permissions = Permission::orderBy('name')->get();

        return view('roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);
        abort_if($role->name === 'owner', 403, 'Los permisos del propietario no se pueden modificar.');
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['required', 'integer', 'exists:permissions,id'],
        ]);
        $before = $role->permissions()->pluck('permissions.id')->all();

        DB::transaction(function () use ($request, $role, $data, $before): void {
            $role->permissions()->sync($data['permission_ids']);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'role.permissions.updated',
                'subject_type' => Role::class,
                'subject_id' => $role->id,
                'before' => ['permission_ids' => $before],
                'after' => ['permission_ids' => $data['permission_ids']],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('roles.index')->with('success', 'Permisos actualizados. Los cambios se aplican en el backend.');
    }
}
