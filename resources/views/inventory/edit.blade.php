@extends('layouts.clinic')
@section('title', 'Editar producto')
@section('content')
<header class="heading"><div><div class="kicker">Inventario</div><h1>Editar producto</h1></div><a class="btn" href="{{ route('inventory.show', $inventoryItem) }}">Volver</a></header><form method="POST" action="{{ route('inventory.update', $inventoryItem) }}">@csrf @method('PUT')<section class="panel">@include('inventory._form')</section><button class="btn primary">Guardar cambios</button></form>
@endsection
