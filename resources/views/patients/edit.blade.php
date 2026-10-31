@extends('layouts.clinic')
@section('title', 'Editar paciente')
@section('content')
<header class="heading"><div><div class="kicker">{{ $patient->patient_code }}</div><h1>Editar datos del paciente</h1></div><a class="btn" href="{{ route('patients.show', $patient) }}">Volver a ficha</a></header>
<form method="POST" action="{{ route('patients.update', $patient) }}">@csrf @method('PUT')<section class="panel"><h2>Información del paciente</h2>@include('patients._form')</section><div class="actions"><button class="btn primary" type="submit">Guardar cambios</button></div></form>
@endsection
