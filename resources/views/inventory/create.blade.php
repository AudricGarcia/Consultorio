@extends('layouts.clinic')
@section('title', 'Nuevo producto')
@section('content')
<header class="heading"><div><div class="kicker">Inventario</div><h1>Agregar producto</h1></div><a class="btn" href="{{ route('inventory.index') }}">Volver</a></header><form method="POST" action="{{ route('inventory.store') }}">@csrf<section class="panel">@include('inventory._form')</section><button class="btn primary">Guardar producto</button></form>
@endsection
