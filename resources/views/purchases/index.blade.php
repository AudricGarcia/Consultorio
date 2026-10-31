@extends('layouts.clinic')
@section('title', 'Compras')
@section('content')
<header class="heading"><div><div class="kicker">Abastecimiento</div><h1>Órdenes de compra</h1><div class="muted">Recibir una orden incrementa el inventario. El pago al proveedor se registra aparte.</div></div><a class="btn primary" href="{{ route('purchase-orders.create') }}">＋ Nueva orden</a></header>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>N.º</th><th>Fecha</th><th>Proveedor</th><th>Líneas</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->id }}</td><td>{{ $order->ordered_at?->format('d/m/Y') ?: $order->created_at->format('d/m/Y') }}</td><td>{{ $order->supplier->name }}</td><td>{{ $order->items->count() }}</td><td>{{ $order->status }}</td><td><a class="btn small" href="{{ route('purchase-orders.show', $order) }}">Ver orden</a></td></tr>@empty<tr><td colspan="6" class="empty">No hay órdenes de compra.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $orders->links() }}</div></section>
@endsection
