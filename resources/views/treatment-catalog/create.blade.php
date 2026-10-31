@extends('layouts.clinic')
@section('title', 'Nuevo procedimiento')
@section('content')
<header class="heading"><div><div class="kicker">Configuración</div><h1>Agregar procedimiento</h1></div><a class="btn" href="{{ route('treatment-catalog.index') }}">Volver</a></header>
<form method="POST" action="{{ route('treatment-catalog.store') }}">@csrf<section class="panel">@include('treatment-catalog._form')</section><button class="btn primary">Guardar procedimiento</button></form>
@endsection
