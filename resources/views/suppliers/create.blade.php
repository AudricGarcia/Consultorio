@extends('layouts.clinic')
@section('title', 'Nuevo proveedor')
@section('content')
<header class="heading"><div><div class="kicker">Proveedores</div><h1>Agregar proveedor</h1></div><a class="btn" href="{{ route('suppliers.index') }}">Volver</a></header><form method="POST" action="{{ route('suppliers.store') }}">@csrf<section class="panel">@include('suppliers._form')</section><button class="btn primary">Guardar proveedor</button></form>
@endsection
