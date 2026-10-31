<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('users.manage');
        abort_unless(auth()->user()->isOwner(), 403);
        $users = User::with('roles')->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('users.manage');
        abort_unless(auth()->user()->isOwner(), 403);
        $roles = Role::where('name', '!=', 'owner')->orderBy('label')->get();

        return view('users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleIds = $data['role_ids'];
        unset($data['role_ids']);

        DB::transaction(function () use ($data, $roleIds, $request): void {
            $user = User::create($data);
            $user->roles()->sync($roleIds);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'user.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'after' => ['name' => $user->name, 'email' => $user->email, 'role_ids' => $roleIds],
                'ip_address' => $request->ip(),
            ]);

        });

        return redirect()->route('users.index')->with('success', 'Usuario creado. Comparte sus credenciales iniciales de forma segura.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): RedirectResponse
    {
        return redirect()->route('users.edit', $user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        $this->authorize('users.manage');
        abort_unless(auth()->user()->isOwner(), 403);
        abort_if($user->isOwner(), 403, 'La cuenta propietaria se administra desde la consola.');
        $user->load('roles');
        $roles = Role::where('name', '!=', 'owner')->orderBy('label')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('users.manage');
        abort_unless($request->user()->isOwner(), 403);
        abort_if($user->isOwner(), 403, 'La cuenta propietaria no puede modificarse aquí.');
        $data = $request->validated();
        $roleIds = $data['role_ids'];
        unset($data['role_ids']);
        $before = ['name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active, 'role_ids' => $user->roles()->pluck('roles.id')->all()];

        DB::transaction(function () use ($data, $roleIds, $request, $user, $before): void {
            $user->update($data);
            $user->roles()->sync($roleIds);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'user.updated',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'before' => $before,
                'after' => ['name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active, 'role_ids' => $roleIds],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort(405, 'Las cuentas se desactivan para conservar la auditoría.');
    }
}
