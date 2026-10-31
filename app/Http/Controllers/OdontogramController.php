<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDentalFindingRequest;
use App\Http\Requests\UpdateDentalFindingRequest;
use App\Http\Requests\VoidDentalFindingRequest;
use App\Models\AuditLog;
use App\Models\ClinicalRecord;
use App\Models\DentalFinding;
use App\Models\DentalFindingCorrection;
use App\Models\DentalFindingVoid;
use App\Models\Patient;
use App\Support\DentalFdi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OdontogramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Patient $patient): View
    {
        $this->authorize('view', $patient);
        $this->authorize('odontogram.view');
        $data = $request->validate(['tooth' => ['nullable', 'string', Rule::in(DentalFdi::toothCodes())]]);
        $selectedTooth = $data['tooth'] ?? null;
        $findings = $patient->dentalFindings()->with(['recorder:id,name', 'correction.replacementFinding', 'correctedFrom', 'void.user:id,name'])->when($selectedTooth !== null, fn ($query) => $query->where('tooth_code', $selectedTooth))->orderByDesc('created_at')->paginate(30)->withQueryString();
        $latestByTooth = $patient->dentalFindings()->whereDoesntHave('correction')->whereDoesntHave('void')->orderByDesc('created_at')->get()->unique('tooth_code')->keyBy('tooth_code');

        return view('odontogram.index', compact('patient', 'findings', 'latestByTooth', 'selectedTooth'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Patient $patient): View
    {
        $this->authorize('view', $patient);
        $this->authorize('odontogram.create');
        $clinicalRecords = ClinicalRecord::where('patient_id', $patient->id)->latest('encountered_at')->limit(50)->get();

        return view('odontogram.create', compact('patient', 'clinicalRecords'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDentalFindingRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('view', $patient);
        $data = $request->validated();

        if ((int) $data['patient_id'] !== $patient->id) {
            abort(404);
        }

        if (isset($data['clinical_record_id']) && ! $patient->clinicalRecords()->whereKey($data['clinical_record_id'])->exists()) {
            abort(422, 'El registro clínico no corresponde a este paciente.');
        }

        $data['recorded_by'] = $request->user()->id;
        $finding = DentalFinding::create($data);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => 'dental_finding.created',
            'subject_type' => DentalFinding::class,
            'subject_id' => $finding->id,
            'after' => ['patient_id' => $finding->patient_id, 'tooth_code' => $finding->tooth_code, 'finding_type' => $finding->finding_type, 'certainty' => $finding->certainty],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('patients.odontogram.index', $patient)->with('success', 'Hallazgo agregado al historial del odontograma.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient, DentalFinding $finding): View
    {
        abort_unless($finding->patient_id === $patient->id, 404);
        $this->authorize('view', $patient);
        $this->authorize('odontogram.view');
        $finding->load(['recorder:id,name', 'correction.corrector:id,name', 'correction.replacementFinding', 'correctedFrom.corrector:id,name', 'correctedFrom.originalFinding', 'void.user:id,name']);
        if (auth()->user()->can('treatments.view')) {
            $finding->load(['treatmentSessions.professional:id,name', 'treatmentSessions.planItem:id,treatment_plan_id,description', 'treatmentSessions.planItem.plan:id,title']);
        }

        return view('odontogram.show', compact('patient', 'finding'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient, DentalFinding $finding): View
    {
        abort_unless($finding->patient_id === $patient->id, 404);
        $this->authorize('view', $patient);
        $this->authorize('odontogram.create');
        abort_if($finding->correction()->exists(), 409, 'Este hallazgo ya fue corregido. Abre la corrección más reciente.');
        abort_if($finding->void()->exists(), 409, 'Este hallazgo ya fue anulado.');
        $finding->load('correctedFrom.originalFinding');
        $clinicalRecords = ClinicalRecord::where('patient_id', $patient->id)->latest('encountered_at')->limit(50)->get();

        return view('odontogram.edit', compact('patient', 'finding', 'clinicalRecords'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDentalFindingRequest $request, Patient $patient, DentalFinding $finding): RedirectResponse
    {
        abort_unless($finding->patient_id === $patient->id, 404);
        $this->authorize('view', $patient);
        $data = $request->validated();
        if (isset($data['clinical_record_id']) && ! $patient->clinicalRecords()->whereKey($data['clinical_record_id'])->exists()) {
            abort(422, 'El registro clínico no corresponde a este paciente.');
        }

        $reason = $data['correction_reason'];
        unset($data['correction_reason']);
        $data['surfaces'] = $data['surfaces'] ?? [];

        $replacement = DB::transaction(function () use ($request, $patient, $finding, $data, $reason): DentalFinding {
            $original = DentalFinding::query()->whereKey($finding->id)->lockForUpdate()->firstOrFail();
            abort_if($original->correction()->exists(), 409, 'Este hallazgo ya fue corregido. Abre la corrección más reciente.');
            abort_if($original->void()->exists(), 409, 'Este hallazgo ya fue anulado.');

            $replacement = DentalFinding::create([
                ...$data,
                'patient_id' => $patient->id,
                'recorded_by' => $request->user()->id,
            ]);
            DentalFindingCorrection::create([
                'original_finding_id' => $original->id,
                'replacement_finding_id' => $replacement->id,
                'corrected_by' => $request->user()->id,
                'reason' => $reason,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'dental_finding.corrected',
                'subject_type' => DentalFinding::class,
                'subject_id' => $original->id,
                'before' => [
                    'patient_id' => $original->patient_id,
                    'clinical_record_id' => $original->clinical_record_id,
                    'tooth_code' => $original->tooth_code,
                    'surfaces' => $original->surfaces,
                    'finding_type' => $original->finding_type,
                    'certainty' => $original->certainty,
                    'treatment_status' => $original->treatment_status,
                    'description' => $original->description,
                ],
                'after' => [
                    'replacement_finding_id' => $replacement->id,
                    'patient_id' => $replacement->patient_id,
                    'tooth_code' => $replacement->tooth_code,
                    'surfaces' => $replacement->surfaces,
                    'finding_type' => $replacement->finding_type,
                    'certainty' => $replacement->certainty,
                    'treatment_status' => $replacement->treatment_status,
                    'description' => $replacement->description,
                ],
                'reason' => $reason,
                'ip_address' => $request->ip(),
            ]);

            return $replacement;
        });

        return redirect()->route('patients.odontogram.show', [$patient, $replacement])->with('success', 'Corrección guardada. El hallazgo anterior se conserva en el historial.');
    }

    public function annul(VoidDentalFindingRequest $request, Patient $patient, DentalFinding $finding): RedirectResponse
    {
        abort_unless($finding->patient_id === $patient->id, 404);
        $this->authorize('view', $patient);
        $reason = $request->validated('reason');

        DB::transaction(function () use ($request, $finding, $reason): void {
            $original = DentalFinding::query()->whereKey($finding->id)->lockForUpdate()->firstOrFail();
            abort_if($original->correction()->exists(), 409, 'Este hallazgo ya fue corregido. Abre la corrección más reciente.');
            abort_if($original->void()->exists(), 409, 'Este hallazgo ya fue anulado.');

            DentalFindingVoid::create([
                'dental_finding_id' => $original->id,
                'voided_by' => $request->user()->id,
                'reason' => $reason,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'dental_finding.voided',
                'subject_type' => DentalFinding::class,
                'subject_id' => $original->id,
                'before' => [
                    'patient_id' => $original->patient_id,
                    'clinical_record_id' => $original->clinical_record_id,
                    'tooth_code' => $original->tooth_code,
                    'surfaces' => $original->surfaces,
                    'finding_type' => $original->finding_type,
                    'certainty' => $original->certainty,
                    'treatment_status' => $original->treatment_status,
                    'description' => $original->description,
                ],
                'after' => ['status' => 'voided'],
                'reason' => $reason,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('patients.odontogram.show', [$patient, $finding])->with('success', 'Hallazgo anulado por error. Se retiró del odontograma vigente y permanece en el historial.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient, DentalFinding $finding): RedirectResponse
    {
        abort(405, 'Los hallazgos del odontograma no se eliminan.');
    }
}
