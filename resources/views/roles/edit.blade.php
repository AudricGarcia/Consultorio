@extends('layouts.clinic')
@section('title', 'Editar permisos')
@section('content')
<header class="heading"><div><div class="kicker">{{ $role->name }}</div><h1>Permisos: {{ $role->label }}</h1><div class="muted">Revisa la matriz con cuidado: cada permiso habilita una operación del servidor.</div></div><a class="btn" href="{{ route('roles.index') }}">Volver</a></header>
<form method="POST" action="{{ route('roles.update', $role) }}">@csrf @method('PUT')<section class="panel"><div class="grid">@foreach($permissions as $permission)<label class="tag" style="justify-content:flex-start"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids', $role->permissions->pluck('id')->all())))> <span><strong>{{ $permission->label }}</strong><small style="display:block;color:#84999a">{{ $permission->name }}</small></span></label>@endforeach</div></section><button class="btn primary">Guardar permisos</button></form>
@endsection
