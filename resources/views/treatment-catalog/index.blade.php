@extends('layouts.clinic')
@section('title', 'Catálogo de tratamientos')
@section('content')
<header class="heading"><div><div class="kicker">Configuración de precios</div><h1>Catálogo de procedimientos</h1><div class="muted">El precio del catálogo es referencial; cada plan guarda una copia de su precio propuesto.</div></div><a class="btn primary" href="{{ route('treatment-catalog.create') }}">＋ Agregar procedimiento</a></header>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>Código</th><th>Procedimiento</th><th>Precio base</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($catalog as $item)<tr><td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>S/ {{ number_format((float) $item->base_price, 2) }}</td><td>{{ $item->is_active ? 'Activo' : 'Inactivo' }}</td><td><a class="btn small" href="{{ route('treatment-catalog.edit', $item) }}">Editar</a></td></tr>@empty<tr><td colspan="5" class="empty">El catálogo está vacío.</td></tr>@endforelse</tbody></table></div>{{ $catalog->links() }}</section>
@endsection
