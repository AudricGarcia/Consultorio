@extends('layouts.clinic')
@section('title', 'Editar cita')
@section('content')
<header class="heading"><div><div class="kicker">Agenda</div><h1>Actualizar cita</h1></div><a class="btn" href="{{ route('appointments.show', $appointment) }}">Volver</a></header>
<form method="POST" action="{{ route('appointments.update', $appointment) }}">@csrf @method('PUT')<section class="panel"><h2>Datos de la cita</h2>@include('appointments._form')</section><button class="btn primary" type="submit">Guardar cambios</button></form>
@endsection
