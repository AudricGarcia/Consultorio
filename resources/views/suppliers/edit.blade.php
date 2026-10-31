@extends('layouts.clinic')
@section('title', 'Editar proveedor')
@section('content')
<header class="heading"><div><div class="kicker">Proveedores</div><h1>Editar proveedor</h1></div><a class="btn" href="{{ route('suppliers.index') }}">Volver</a></header><form method="POST" action="{{ route('suppliers.update', $supplier) }}">@csrf @method('PUT')<section class="panel">@include('suppliers._form')</section><button class="btn primary">Guardar cambios</button></form>
@endsection
