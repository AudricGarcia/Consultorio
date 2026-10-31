@extends('layouts.clinic')
@section('title', 'Pacientes')
@section('content')
<header class="heading"><div><div class="kicker">Directorio clínico</div><h1>Pacientes</h1><div class="muted">Busca por nombre, documento, teléfono o código de paciente.</div></div>@can('create', App\Models\Patient::class)<a class="btn primary" href="{{ route('patients.create') }}">＋ Registrar paciente</a>@endcan</header>
<livewire:patients.patient-directory />
@endsection
