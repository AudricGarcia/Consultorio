@extends('layouts.clinic')
@section('title', 'Editar procedimiento')
@section('content')
<header class="heading"><div><div class="kicker">Configuración</div><h1>Editar procedimiento</h1></div><a class="btn" href="{{ route('treatment-catalog.index') }}">Volver</a></header>
<form method="POST" action="{{ route('treatment-catalog.update', $treatment) }}">@csrf @method('PUT')<section class="panel">@include('treatment-catalog._form')</section><button class="btn primary">Guardar cambios</button></form>
@endsection
