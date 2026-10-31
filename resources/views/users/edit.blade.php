@extends('layouts.clinic')
@section('title', 'Editar usuario')
@section('content')
<header class="heading"><div><div class="kicker">Administración de accesos</div><h1>Editar usuario</h1><div class="muted">{{ $user->name }} · {{ $user->email }}</div></div><a class="btn" href="{{ route('users.index') }}">Volver</a></header>
<form method="POST" action="{{ route('users.update', $user) }}">@csrf @method('PUT')<section class="panel"><h2>Cuenta y permisos</h2>@include('users._form')</section><button class="btn primary" type="submit">Guardar cambios</button></form>
@endsection
