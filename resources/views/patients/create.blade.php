@extends('layouts.clinic')
@section('title', 'Registrar paciente')
@section('content')
<header class="heading"><div><div class="kicker">Directorio clínico</div><h1>Registrar paciente</h1><div class="muted">Los datos sensibles se cifran al guardarse.</div></div><a class="btn" href="{{ route('patients.index') }}">Volver</a></header>
<form method="POST" action="{{ route('patients.store') }}">@csrf<section class="panel"><h2>Información del paciente</h2>@include('patients._form')</section><div class="actions"><button class="btn primary" type="submit">Guardar paciente</button><a class="btn" href="{{ route('patients.index') }}">Cancelar</a></div></form>
@endsection
