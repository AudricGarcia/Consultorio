<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $this->authorize('appointments.view');
        $date = ($request->date('date') ?? today())->toDateString();
        $mode = in_array($request->query('view'), ['day', 'week', 'month'], true) ? $request->query('view') : 'day';
        $baseDate = Carbon::parse($date);
        $from = match ($mode) {
            'week' => $baseDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
            'month' => $baseDate->copy()->startOfMonth()->startOfDay(),
            default => $baseDate->copy()->startOfDay(),
        };
        $to = match ($mode) {
            'week' => $from->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            'month' => $from->copy()->endOfMonth()->endOfDay(),
            default => $from->copy()->endOfDay(),
        };
        $appointments = Appointment::with(['patient', 'professional:id,name'])
            ->when(auth()->user()->hasRole('dentist'), fn ($query) => $query->where('professional_id', auth()->id()))
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->paginate($mode === 'month' ? 50 : 30)
            ->withQueryString();

        return view('appointments.index', compact('appointments', 'date', 'mode', 'from', 'to'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('appointments.manage');
        $patients = Patient::orderBy('last_name')->orderBy('first_name')->get(['id', 'patient_code', 'first_name', 'last_name']);
        $professionals = User::whereHas('roles', fn ($query) => $query->whereIn('name', ['owner', 'dentist']))->orderBy('name')->get(['id', 'name']);

        return view('appointments.create', compact('patients', 'professionals'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $patient = Patient::findOrFail($data['patient_id']);
        $this->authorize('view', $patient);

        $data['created_by'] = $request->user()->id;
        $appointment = DB::transaction(function () use ($data, $request): Appointment {
            User::whereKey($data['professional_id'])->lockForUpdate()->firstOrFail();

            $conflict = Appointment::query()
                ->where('professional_id', $data['professional_id'])
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where('starts_at', '<', $data['ends_at'])
                ->where('ends_at', '>', $data['starts_at'])
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'El profesional ya tiene una cita en ese horario.']);
            }

            $appointment = Appointment::create($data);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'appointment.created',
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'after' => $appointment->only(['patient_id', 'professional_id', 'starts_at', 'ends_at', 'status']),
                'ip_address' => $request->ip(),
            ]);

            return $appointment;
        });

        return redirect()->route('appointments.index', ['date' => $appointment->starts_at->toDateString()])->with('success', 'Cita programada.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment): View
    {
        $this->authorize('appointments.view');
        $this->authorize('view', $appointment->patient);
        abort_if(auth()->user()->hasRole('dentist') && (int) $appointment->professional_id !== (int) auth()->id(), 403);
        $appointment->load(['patient', 'professional:id,name', 'creator:id,name']);

        return view('appointments.show', compact('appointment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Appointment $appointment): View
    {
        $this->authorize('appointments.manage');
        $patients = Patient::orderBy('last_name')->orderBy('first_name')->get(['id', 'patient_code', 'first_name', 'last_name']);
        $professionals = User::whereHas('roles', fn ($query) => $query->whereIn('name', ['owner', 'dentist']))->orderBy('name')->get(['id', 'name']);

        return view('appointments.edit', compact('appointment', 'patients', 'professionals'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('appointments.manage');
        $data = $request->validated();
        $this->authorize('view', Patient::findOrFail($data['patient_id']));
        DB::transaction(function () use ($data, $request, $appointment): void {
            User::whereKey($data['professional_id'])->lockForUpdate()->firstOrFail();
            $overlap = Appointment::where('professional_id', $data['professional_id'])
                ->where('id', '!=', $appointment->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where('starts_at', '<', $data['ends_at'])
                ->where('ends_at', '>', $data['starts_at'])
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'El profesional ya tiene una cita en ese horario.']);
            }

            $before = $appointment->only(['patient_id', 'professional_id', 'starts_at', 'ends_at', 'status']);
            $appointment->update($data);
            if ($before['starts_at'] != $appointment->starts_at || in_array($appointment->status, ['cancelled', 'no_show'], true)) {
                $appointment->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'appointment.updated', 'subject_type' => Appointment::class, 'subject_id' => $appointment->id, 'before' => $before, 'after' => $appointment->only(array_keys($before)), 'ip_address' => $request->ip()]);
        });

        return redirect()->route('appointments.show', $appointment)->with('success', 'Cita actualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('appointments.manage');
        $appointment->update(['status' => 'cancelled']);
        $appointment->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'appointment.cancelled', 'subject_type' => Appointment::class, 'subject_id' => $appointment->id, 'after' => ['status' => 'cancelled'], 'ip_address' => $request->ip()]);

        return redirect()->route('appointments.index')->with('success', 'Cita cancelada; el registro se conserva.');
    }
}
