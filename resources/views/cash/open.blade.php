@extends('layouts.clinic')
@section('title', 'Abrir caja')
@section('content')
<header class="heading"><div><div class="kicker">Caja · Nuevo turno</div><h1>Abrir caja</h1><div class="muted">El saldo de apertura se usa para calcular el saldo teórico.</div></div><a class="btn" href="{{ route('cash.index') }}">Volver</a></header>
<form method="POST" action="{{ route('cash.store') }}">@csrf<section class="panel"><div class="field"><label>Saldo inicial (PEN) *</label><input name="opening_balance" type="number" step="0.01" min="0" value="0.00" required></div></section><button class="btn primary">Abrir turno</button></form>
@endsection
