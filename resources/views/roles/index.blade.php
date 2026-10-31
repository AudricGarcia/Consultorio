@extends('layouts.clinic')
@section('title', 'Roles y permisos')
@section('content')
<header class="heading"><div><div class="kicker">Control de acceso</div><h1>Roles y permisos</h1><div class="muted">El rol propietario conserva acceso completo y no se puede asignar ni reducir desde el panel.</div></div></header>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>Rol</th><th>Usuarios</th><th>Permisos</th><th></th></tr></thead><tbody>@foreach($roles as $role)<tr><td><strong>{{ $role->label }}</strong><small style="display:block;color:#879a9b">{{ $role->name }}</small></td><td>{{ $role->users_count }}</td><td>{{ $role->permissions->count() }}</td><td><a class="btn small" href="{{ route('roles.edit', $role) }}">Editar permisos</a></td></tr>@endforeach</tbody></table></div></section>
@endsection
