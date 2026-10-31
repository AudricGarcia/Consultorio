<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalRecordRequest;
use App\Models\AuditLog;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Support\DentalFdi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClinicalRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorize('clinical.view');
        $records = ClinicalRecord::with(['patient', 'professional:id,name'])
            ->when(auth()->user()->hasRole('dentist'), fn ($query) => $query->where('professional_id', auth()->id()))
            ->when($request->integer('patient_id'), fn ($query, $id) => $query->where('patient_id', $id))
            ->orderByDesc('encountered_at')->paginate(20)->withQueryString();

        return view('clinical.index', compact('records'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        $this->authorize('clinical.create');
        $patients = Patient::orderBy('last_name')->orderBy('first_name')->get(['id', 'patient_code', 'first_name', 'last_name']);
        $selectedPatientId = $request->integer('patient_id');

        $toothCodes = DentalFdi::toothCodes();

        return view('clinical.create', compact('patients', 'selectedPatientId', 'toothCodes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClinicalRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $patient = Patient::findOrFail($data['patient_id']);
        $this->authorize('view', $patient);
        $structuredDiagnoses = $data['structured_diagnoses'] ?? [];
        unset($data['structured_diagnoses']);
        $data['professional_id'] = $request->user()->id;
        $data['status'] = 'draft';
        $record = DB::transaction(function () use ($data, $structuredDiagnoses, $request): ClinicalRecord {
            $record = ClinicalRecord::create($data);
            foreach ($structuredDiagnoses as $diagnosis) {
                $record->structuredDiagnoses()->create($diagnosis);
            }
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'clinical_record.created',
                'subject_type' => ClinicalRecord::class,
                'subject_id' => $record->id,
                'after' => ['patient_id' => $record->patient_id, 'record_type' => $record->record_type, 'status' => $record->status, 'structured_diagnosis_count' => count($structuredDiagnoses)],
                'ip_address' => $request->ip(),
            ]);

            return $record;
        });

        return redirect()->route('patients.show', $patient)->with('success', 'Registro clínico guardado como borrador.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ClinicalRecord $clinicalRecord): View
    {
        $this->authorize('clinical.view');
        $this->authorize('view', $clinicalRecord->patient);
        abort_if(auth()->user()->hasRole('dentist') && (int) $clinicalRecord->professional_id !== (int) auth()->id(), 403);
        $clinicalRecord->load(['patient', 'professional:id,name', 'dentalFindings', 'structuredDiagnoses', 'media']);

        return view('clinical.show', ['record' => $clinicalRecord]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClinicalRecord $clinicalRecord): RedirectResponse
    {
        $this->authorize('clinical.view');

        return redirect()->route('clinical-records.show', $clinicalRecord)->with('info', 'Los registros clínicos son inmutables; crea una nota adicional para corregir o complementar.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClinicalRecord $clinicalRecord): RedirectResponse
    {
        $this->authorize('clinical.view');
        abort(405, 'Los registros clínicos no se sobrescriben.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClinicalRecord $clinicalRecord): RedirectResponse
    {
        $this->authorize('clinical.view');
        abort(405, 'Los registros clínicos no se eliminan.');
    }

    public function validateRecord(Request $request, ClinicalRecord $clinicalRecord): RedirectResponse
    {
        $this->authorize('clinical.validate');
        abort_unless($clinicalRecord->status === 'draft', 409);
        $clinicalRecord->update(['status' => 'validated', 'validated_by' => $request->user()->id, 'validated_at' => now()]);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'clinical_record.validated', 'subject_type' => ClinicalRecord::class, 'subject_id' => $clinicalRecord->id, 'after' => ['status' => 'validated'], 'ip_address' => $request->ip()]);

        return back()->with('success', 'Registro clínico validado; el contenido queda inmutable.');
    }
}
