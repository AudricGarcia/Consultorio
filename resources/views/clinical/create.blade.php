@extends('layouts.clinic')
@section('title', 'Registrar consulta')
@section('content')
<header class="heading"><div><div class="kicker">Historia clínica · Nuevo registro</div><h1>Registrar consulta</h1><div class="muted">El registro se guardará como borrador inmutable.</div></div></header>
<form method="POST" action="{{ route('clinical-records.store') }}">@csrf<section class="panel"><div class="formgrid">
<div class="field wide"><label for="patient_id">Paciente *</label><select name="patient_id" id="patient_id" required><option value="">Selecciona un paciente</option>@foreach($patients as $patient)<option value="{{ $patient->id }}" @selected(old('patient_id', $selectedPatientId) == $patient->id)>{{ $patient->patient_code }} · {{ $patient->last_name }}, {{ $patient->first_name }}</option>@endforeach</select></div>
<div class="field"><label for="encountered_at">Fecha y hora *</label><input id="encountered_at" type="datetime-local" name="encountered_at" value="{{ old('encountered_at', now()->format('Y-m-d\TH:i')) }}" required></div>
<div class="field"><label for="record_type">Tipo de registro *</label><select name="record_type" id="record_type">@foreach(['encounter'=>'Consulta','note'=>'Nota clínica','prescription'=>'Receta','consent'=>'Consentimiento','follow_up'=>'Seguimiento'] as $value=>$label)<option value="{{ $value }}" @selected(old('record_type') === $value)>{{ $label }}</option>@endforeach</select></div>
@foreach(['reason'=>'Motivo de consulta','anamnesis'=>'Anamnesis y antecedentes','examination'=>'Examen clínico y hallazgos','diagnoses'=>'Notas de diagnóstico','plan'=>'Plan de tratamiento','evolution'=>'Evolución e indicaciones'] as $field=>$label)<div class="field wide"><label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}">{{ old($field) }}</textarea></div>@endforeach
<div class="field wide"><label>Diagnósticos estructurados</label><div id="diagnosis-list">@foreach(old('structured_diagnoses', []) as $index => $diagnosis)<div class="panel diagnosis-row" style="background:#f9fbfb"><div class="formgrid"><div class="field wide"><label>Diagnóstico *</label><input name="structured_diagnoses[{{ $index }}][diagnosis]" value="{{ $diagnosis['diagnosis'] ?? '' }}" maxlength="160" required></div><div class="field"><label>Pieza FDI</label><select name="structured_diagnoses[{{ $index }}][tooth_code]"><option value="">General / no aplica</option>@foreach($toothCodes as $tooth)<option value="{{ $tooth }}" @selected(($diagnosis['tooth_code'] ?? '') === $tooth)>{{ $tooth }}</option>@endforeach</select></div><div class="field"><label>Certeza clínica</label><select name="structured_diagnoses[{{ $index }}][certainty]">@foreach(['confirmed'=>'Confirmado','suspected'=>'Sospechado','differential'=>'Diferencial'] as $value => $label)<option value="{{ $value }}" @selected(($diagnosis['certainty'] ?? 'confirmed') === $value)>{{ $label }}</option>@endforeach</select></div><div class="field wide"><label>Observaciones</label><input name="structured_diagnoses[{{ $index }}][notes]" value="{{ $diagnosis['notes'] ?? '' }}" maxlength="1000"></div><div class="field"><button type="button" class="btn danger remove-diagnosis">Quitar</button></div></div></div>@endforeach</div><button type="button" class="btn" id="add-diagnosis">＋ Añadir diagnóstico</button><p class="hint">La descripción y las observaciones se cifran. Puedes vincular cada diagnóstico a una pieza dental FDI y registrar su certeza.</p></div>
</div></section><button class="btn primary" type="submit">Guardar registro</button> <a class="btn" href="{{ route('patients.index') }}">Cancelar</a></form>
<template id="diagnosis-template"><div class="panel diagnosis-row" style="background:#f9fbfb"><div class="formgrid"><div class="field wide"><label>Diagnóstico *</label><input name="structured_diagnoses[__INDEX__][diagnosis]" maxlength="160" required></div><div class="field"><label>Pieza FDI</label><select name="structured_diagnoses[__INDEX__][tooth_code]"><option value="">General / no aplica</option>@foreach($toothCodes as $tooth)<option value="{{ $tooth }}">{{ $tooth }}</option>@endforeach</select></div><div class="field"><label>Certeza clínica</label><select name="structured_diagnoses[__INDEX__][certainty]">@foreach(['confirmed'=>'Confirmado','suspected'=>'Sospechado','differential'=>'Diferencial'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field wide"><label>Observaciones</label><input name="structured_diagnoses[__INDEX__][notes]" maxlength="1000"></div><div class="field"><button type="button" class="btn danger remove-diagnosis">Quitar</button></div></div></div></template>
<script>
    (() => {
        const list = document.getElementById('diagnosis-list');
        const template = document.getElementById('diagnosis-template');
        let nextIndex = {{ count(old('structured_diagnoses', [])) }};
        document.getElementById('add-diagnosis').addEventListener('click', () => {
            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        });
        list.addEventListener('click', (event) => {
            if (event.target.closest('.remove-diagnosis')) {
                event.target.closest('.diagnosis-row').remove();
            }
        });
    })();
</script>
@endsection
