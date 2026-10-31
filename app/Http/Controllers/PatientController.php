<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Patient::class);

        return view('patients.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Patient::class);

        return view('patients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePatientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (! $request->user()->can('clinical.view')) {
            unset($data['identity_document'], $data['address'], $data['emergency_contact_name'], $data['emergency_contact_phone'], $data['medical_history'], $data['allergies'], $data['medications'], $data['dental_history'], $data['clinical_alerts'], $data['voice_assistant_consent']);
        }
        unset($data['consent']);
        $data['patient_code'] = 'PAC-'.Str::upper(Str::random(8));
        $data['created_by'] = $request->user()->id;
        $data['consent_at'] = now();
        $data['reminder_consent'] = (bool) ($data['reminder_consent'] ?? false);
        $data['reminder_consent_at'] = $data['reminder_consent'] ? now() : null;
        $data['voice_assistant_consent'] = (bool) ($data['voice_assistant_consent'] ?? false);
        $data['voice_assistant_consent_at'] = $data['voice_assistant_consent'] ? now() : null;
        $data['identity_document_hash'] = filled($data['identity_document'] ?? null)
            ? $this->documentHash($data['identity_document'])
            : null;

        try {
            $patient = DB::transaction(function () use ($data): Patient {
                $patient = Patient::create($data);
                $this->audit('patient.created', $patient, null, $patient->only(['patient_code', 'first_name', 'last_name', 'voice_assistant_consent']));

                return $patient;
            });
        } catch (QueryException $exception) {
            if (filled($data['identity_document_hash']) && str_contains($exception->getMessage(), 'identity_document_hash')) {
                throw ValidationException::withMessages(['identity_document' => 'Ya existe un paciente con ese documento.']);
            }

            throw $exception;
        }

        return redirect()->route('patients.show', $patient)->with('success', 'Paciente registrado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient): View
    {
        $this->authorize('view', $patient);
        if (auth()->user()->can('appointments.view')) {
            $patient->load(['appointments' => fn ($query) => $query->when(auth()->user()->hasRole('dentist'), fn ($appointments) => $appointments->where('professional_id', auth()->id()))->with('professional:id,name')->orderByDesc('starts_at')->limit(10)]);
        }

        if (auth()->user()->can('clinical.view')) {
            $patient->load(['clinicalRecords' => fn ($query) => $query->when(auth()->user()->hasRole('dentist'), fn ($records) => $records->where('professional_id', auth()->id()))->with('professional:id,name')->orderByDesc('encountered_at')->limit(20)]);
        }

        if (auth()->user()->can('odontogram.view')) {
            $patient->load(['dentalFindings' => fn ($query) => $query->with('recorder:id,name')->orderByDesc('created_at')->limit(30)]);
        }

        if (auth()->user()->can('payments.view')) {
            $patient->load(['payments' => fn ($query) => $query->with('receiver:id,name')->orderByDesc('paid_at')->limit(20)]);
        }

        if (auth()->user()->can('treatments.view')) {
            $patient->load(['treatmentPlans' => fn ($query) => $query->when(auth()->user()->hasRole('dentist'), fn ($plans) => $plans->where('professional_id', auth()->id()))->with('professional:id,name', 'items')->orderByDesc('created_at')->limit(20)]);
        }

        return view('patients.show', compact('patient'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient): View
    {
        $this->authorize('update', $patient);

        return view('patients.edit', compact('patient'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $data = $request->validated();
        if (! $request->user()->can('clinical.view')) {
            unset($data['identity_document'], $data['address'], $data['emergency_contact_name'], $data['emergency_contact_phone'], $data['medical_history'], $data['allergies'], $data['medications'], $data['dental_history'], $data['clinical_alerts'], $data['voice_assistant_consent']);
        }
        $before = $patient->only(['first_name', 'last_name', 'phone', 'email', 'clinical_alerts', 'reminder_consent', 'voice_assistant_consent']);
        if (array_key_exists('identity_document', $data)) {
            $data['identity_document_hash'] = filled($data['identity_document']) ? $this->documentHash($data['identity_document']) : null;
        }
        if (array_key_exists('reminder_consent', $data)) {
            $data['reminder_consent'] = (bool) $data['reminder_consent'];
            $data['reminder_consent_at'] = $data['reminder_consent'] ? now() : null;
        }
        if (array_key_exists('voice_assistant_consent', $data)) {
            $data['voice_assistant_consent'] = (bool) $data['voice_assistant_consent'];
            $data['voice_assistant_consent_at'] = $data['voice_assistant_consent'] ? now() : null;
        }

        try {
            DB::transaction(function () use ($patient, $data, $before): void {
                $patient->update($data);
                $this->audit('patient.updated', $patient, $before, $patient->only(['first_name', 'last_name', 'phone', 'email', 'clinical_alerts', 'reminder_consent', 'voice_assistant_consent']));
            });
        } catch (QueryException $exception) {
            if (filled($data['identity_document_hash']) && str_contains($exception->getMessage(), 'identity_document_hash')) {
                throw ValidationException::withMessages(['identity_document' => 'Ya existe un paciente con ese documento.']);
            }

            throw $exception;
        }

        return redirect()->route('patients.show', $patient)->with('success', 'Datos del paciente actualizados.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);
        DB::transaction(function () use ($patient): void {
            $patient->delete();
            $this->audit('patient.archived', $patient, null, ['patient_code' => $patient->patient_code]);
        });

        return redirect()->route('patients.index')->with('success', 'Paciente archivado; su historial se conserva.');
    }

    private function documentHash(string $document): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($document)), (string) config('app.key'));
    }

    private function audit(string $event, Patient $patient, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
        ]);
    }
}
