@extends('layouts.clinic')
@section('title', 'Proveedores')
@section('content')
<header class="heading"><div><div class="kicker">Compras y abastecimiento</div><h1>Proveedores</h1></div><a class="btn primary" href="{{ route('suppliers.create') }}">＋ Nuevo proveedor</a></header>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>Proveedor</th><th>RUC / documento</th><th>Contacto</th><th>Teléfono</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($suppliers as $supplier)<tr><td>{{ $supplier->name }}</td><td>{{ $supplier->tax_id ?: '—' }}</td><td>{{ $supplier->contact_name ?: $supplier->email ?: '—' }}</td><td>{{ $supplier->phone ?: '—' }}</td><td>{{ $supplier->is_active ? 'Activo' : 'Inactivo' }}</td><td><a class="btn small" href="{{ route('suppliers.edit', $supplier) }}">Editar</a></td></tr>@empty<tr><td colspan="6" class="empty">No hay proveedores.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $suppliers->links() }}</div></section>
@endsection
