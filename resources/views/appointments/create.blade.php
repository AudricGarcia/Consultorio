@extends('layouts.clinic')
@section('title', 'Nueva cita')
@section('content')
<header class="heading"><div><div class="kicker">Agenda</div><h1>Programar cita</h1><div class="muted">Se validará que el horario del profesional esté libre.</div></div><a class="btn" href="{{ route('appointments.index') }}">Volver</a></header>
<form method="POST" action="{{ route('appointments.store') }}">@csrf<section class="panel"><h2>Datos de la cita</h2>@include('appointments._form')</section><button class="btn primary" type="submit">Guardar cita</button></form>
@endsection
