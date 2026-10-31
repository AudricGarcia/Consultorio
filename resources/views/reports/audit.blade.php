@extends('layouts.clinic')
@section('title', 'Auditoría')
@section('content')
<div class="heading"><div><div class="kicker">Seguridad</div><h1>Registro de auditoría</h1><div class="muted">Eventos del sistema. Los valores clínicos cifrados no se muestran en esta lista.</div></div></div>
<section class="panel"><form method="GET" class="actions"><input name="event" value="{{ request('event') }}" placeholder="Filtrar por evento" style="border:1px solid #dce7e6;border-radius:8px;padding:9px 11px"><button class="btn">Filtrar</button></form><div class="tablewrap" style="margin-top:14px"><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Tipo de registro</th><th>IP</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $log->user?->name ?? 'Usuario eliminado' }}</td><td>{{ $log->event }}</td><td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td><td>{{ $log->ip_address ?? '—' }}</td></tr>@empty<tr><td colspan="5">No hay eventos para mostrar.</td></tr>@endforelse</tbody></table></div>{{ $logs->links() }}</section>
@endsection
