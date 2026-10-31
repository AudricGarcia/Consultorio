@extends('layouts.clinic')
@section('title', 'Usuarios y roles')
@section('content')
<header class="heading"><div><div class="kicker">Administración de accesos</div><h1>Usuarios y roles</h1><div class="muted">Los permisos se aplican en el servidor para cada operación.</div></div><a class="btn primary" href="{{ route('users.create') }}">＋ Crear usuario</a></header>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Roles</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->roles->pluck('label')->join(', ') ?: 'Sin rol' }}</td><td><span class="tag">{{ $user->is_active ? 'Activo' : 'Desactivado' }}</span></td><td>@if(!$user->isOwner())<a class="btn small" href="{{ route('users.edit', $user) }}">Editar acceso</a>@else<span class="muted">Cuenta propietaria protegida</span>@endif</td></tr>@empty<tr><td colspan="5" class="empty">Aún no hay usuarios.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $users->links() }}</div></section>
@endsection
