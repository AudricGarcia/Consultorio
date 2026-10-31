@extends('layouts.clinic')
@section('title', 'Crear usuario')
@section('content')
<header class="heading"><div><div class="kicker">Acceso al sistema</div><h1>Crear usuario</h1><div class="muted">Las contraseñas se almacenan con el hash seguro de Laravel.</div></div><a class="btn" href="{{ route('users.index') }}">Volver</a></header>
<form method="POST" action="{{ route('users.store') }}">@csrf<section class="panel"><h2>Cuenta y permisos</h2>@include('users._form')</section><button class="btn primary" type="submit">Crear cuenta</button></form>
@endsection
