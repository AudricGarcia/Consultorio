@extends('layouts.clinic')
@section('title', 'Tratamientos')
@section('content')
<header class="heading"><div><div class="kicker">Planes de atención</div><h1>Tratamientos</h1><div class="muted">Los presupuestos propuestos no se consideran ingresos.</div></div>@can('treatments.manage')<a class="btn primary" href="{{ route('treatment-plans.create') }}">＋ Nuevo plan</a>@endcan</header>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>Fecha</th><th>Paciente</th><th>Plan</th><th>Procedimientos</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($plans as $plan)<tr><td>{{ $plan->created_at->format('d/m/Y') }}</td><td>{{ $plan->patient->last_name }}, {{ $plan->patient->first_name }}</td><td>{{ $plan->title }}</td><td>{{ $plan->items->count() }}</td><td><span class="tag">{{ $plan->status }}</span></td><td><a class="btn small" href="{{ route('treatment-plans.show', $plan) }}">Ver plan</a></td></tr>@empty<tr><td colspan="6" class="empty">No hay planes de tratamiento.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $plans->links() }}</div></section>
@endsection
