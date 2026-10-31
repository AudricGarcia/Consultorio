<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTreatmentPlanRequest;
use App\Http\Requests\StoreTreatmentSessionRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DentalFinding;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\TreatmentCatalog;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentSession;
use App\Support\DentalFdi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TreatmentPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('treatments.view');
        $plans = TreatmentPlan::with(['patient', 'professional:id,name', 'items'])
            ->when(auth()->user()->hasRole('dentist'), fn ($query) => $query->where('professional_id', auth()->id()))
            ->orderByDesc('created_at')->paginate(20);

        return view('treatments.index', compact('plans'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        $this->authorize('treatments.manage');
        $patients = Patient::orderBy('last_name')->orderBy('first_name')->get(['id', 'patient_code', 'first_name', 'last_name']);
        $catalog = TreatmentCatalog::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'base_price']);
        $catalogData = $catalog->map(fn (TreatmentCatalog $procedure): array => ['id' => $procedure->id, 'code' => $procedure->code, 'name' => $procedure->name, 'price' => $procedure->base_price])->all();

        return view('treatments.create', ['patients' => $patients, 'selectedPatientId' => $request->integer('patient_id'), 'catalog' => $catalog, 'catalogData' => $catalogData]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTreatmentPlanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $patient = Patient::findOrFail($data['patient_id']);
        $this->authorize('view', $patient);
        $items = $data['items'];
        unset($data['items']);
        $data['professional_id'] = $request->user()->id;
        $data['status'] = 'proposed';

        $plan = DB::transaction(function () use ($data, $items, $request): TreatmentPlan {
            $plan = TreatmentPlan::create($data);
            $plan->items()->createMany($items);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'treatment_plan.created',
                'subject_type' => TreatmentPlan::class,
                'subject_id' => $plan->id,
                'after' => ['patient_id' => $plan->patient_id, 'status' => $plan->status, 'item_count' => count($items)],
                'ip_address' => $request->ip(),
            ]);

            return $plan;
        });

        return redirect()->route('treatment-plans.show', $plan)->with('success', 'Plan de tratamiento preparado para revisión del paciente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(TreatmentPlan $treatmentPlan): View
    {
        $this->authorize('treatments.view');
        $this->authorize('view', $treatmentPlan->patient);
        abort_if(auth()->user()->hasRole('dentist') && (int) $treatmentPlan->professional_id !== (int) auth()->id(), 403);
        $treatmentPlan->load(['patient', 'professional:id,name', 'items.sessions.professional:id,name', 'items.sessions.dentalFinding', 'items.sessions.materialConsumptions.item', 'items.sessions.materialConsumptions.recorder:id,name', 'items.sessions.materialConsumptions.movements.batch', 'payments']);
        $dentalFindings = $treatmentPlan->patient->dentalFindings()->latest()->limit(100)->get(['id', 'tooth_code', 'surfaces', 'finding_type', 'created_at']);
        $appointments = Appointment::query()->where('patient_id', $treatmentPlan->patient_id)->where('status', 'completed')
            ->when(auth()->user()->hasRole('dentist'), fn ($query) => $query->where('professional_id', auth()->id()))
            ->orderByDesc('starts_at')->limit(20)->get(['id', 'starts_at']);

        $inventoryItems = auth()->user()->can('inventory.consume')
            ? InventoryItem::query()->where('is_active', true)->select('inventory_items.id', 'inventory_items.name', 'inventory_items.unit')->selectSub(
                DB::table('inventory_batches')->join('inventory_movements', 'inventory_movements.inventory_batch_id', '=', 'inventory_batches.id')
                    ->selectRaw("COALESCE(SUM(CASE WHEN inventory_movements.direction = 'in' THEN inventory_movements.quantity ELSE -inventory_movements.quantity END), 0)")
                    ->whereColumn('inventory_batches.inventory_item_id', 'inventory_items.id')
                    ->where(fn ($query) => $query->whereNull('inventory_batches.expires_on')->orWhereDate('inventory_batches.expires_on', '>=', today())),
                'available_stock'
            )->orderBy('name')->get()
            : collect();
        $hasAvailableMaterials = $inventoryItems->contains(fn (InventoryItem $material): bool => (float) $material->available_stock > 0);
        $toothCodes = DentalFdi::toothCodes();

        return view('treatments.show', ['plan' => $treatmentPlan, 'appointments' => $appointments, 'dentalFindings' => $dentalFindings, 'toothCodes' => $toothCodes, 'inventoryItems' => $inventoryItems, 'hasAvailableMaterials' => $hasAvailableMaterials]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TreatmentPlan $treatmentPlan): RedirectResponse
    {
        $this->authorize('treatments.view');

        return redirect()->route('treatment-plans.show', $treatmentPlan)->with('info', 'Los planes aceptados o compartidos se corrigen mediante un nuevo plan, conservando el historial.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        $this->authorize('treatments.view');
        abort(405, 'Los planes de tratamiento no se sobrescriben.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TreatmentPlan $treatmentPlan): RedirectResponse
    {
        $this->authorize('treatments.view');
        abort(405, 'Los planes de tratamiento se conservan como historial.');
    }

    public function accept(Request $request, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        $this->authorize('treatments.manage');
        $data = $request->validate([
            'installment_count' => ['nullable', 'integer', 'between:1,36'],
            'first_due_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $installmentCount = (int) ($data['installment_count'] ?? 1);
        $firstDueAt = Carbon::parse($data['first_due_at'] ?? today()->toDateString());

        DB::transaction(function () use ($request, $treatmentPlan, $installmentCount, $firstDueAt): void {
            $plan = TreatmentPlan::query()->whereKey($treatmentPlan->id)->lockForUpdate()->with('items')->firstOrFail();
            abort_unless($plan->status === 'proposed', 409);
            $totalCents = (int) round($plan->items->sum(fn (TreatmentPlanItem $item): float => (float) $item->quantity * (float) $item->unit_price) * 100);
            if ($totalCents < $installmentCount) {
                throw ValidationException::withMessages(['installment_count' => 'El número de cuotas no puede superar el total en céntimos del plan.']);
            }

            $baseCents = intdiv($totalCents, $installmentCount);
            $extraCents = $totalCents % $installmentCount;
            for ($index = 0; $index < $installmentCount; $index++) {
                $cents = $baseCents + ($index < $extraCents ? 1 : 0);
                $plan->installments()->create([
                    'installment_number' => $index + 1,
                    'due_at' => $firstDueAt->copy()->addMonthsNoOverflow($index)->toDateString(),
                    'amount' => number_format($cents / 100, 2, '.', ''),
                ]);
            }
            $plan->update(['status' => 'accepted', 'accepted_at' => now()]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'treatment_plan.accepted',
                'subject_type' => TreatmentPlan::class,
                'subject_id' => $plan->id,
                'after' => ['status' => 'accepted', 'installment_count' => $installmentCount, 'total_cents' => $totalCents],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Plan aceptado y calendario de cuotas registrado.');
    }

    public function storeSession(StoreTreatmentSessionRequest $request, TreatmentPlan $treatmentPlan, TreatmentPlanItem $item): RedirectResponse
    {
        $this->authorize('view', $treatmentPlan->patient);
        abort_if($request->user()->hasRole('dentist') && (int) $treatmentPlan->professional_id !== (int) $request->user()->id, 403);
        abort_unless($treatmentPlan->items()->whereKey($item->id)->exists(), 404);
        abort_unless(in_array($treatmentPlan->status, ['accepted', 'in_progress'], true), 409);
        $data = $request->validated();
        $dentalFinding = null;
        if (! empty($data['dental_finding_id'])) {
            $dentalFinding = DentalFinding::where('patient_id', $treatmentPlan->patient_id)->findOrFail($data['dental_finding_id']);
            if (filled($data['tooth_code'] ?? null) && $data['tooth_code'] !== $dentalFinding->tooth_code) {
                throw ValidationException::withMessages(['dental_finding_id' => 'El hallazgo seleccionado corresponde a otra pieza dental.']);
            }
            $data['tooth_code'] ??= $dentalFinding->tooth_code;
            $data['surfaces'] = $data['surfaces'] ?? $dentalFinding->surfaces;
        }
        if (filled($data['surfaces'] ?? null) && blank($data['tooth_code'] ?? null)) {
            throw ValidationException::withMessages(['tooth_code' => 'Selecciona una pieza dental antes de indicar superficies.']);
        }
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::whereKey($data['appointment_id'])->where('patient_id', $treatmentPlan->patient_id)->where('status', 'completed')->firstOrFail();
            abort_if($request->user()->hasRole('dentist') && (int) $appointment->professional_id !== (int) $request->user()->id, 403);
        }

        DB::transaction(function () use ($request, $treatmentPlan, $item, $data, $dentalFinding): void {
            $plan = TreatmentPlan::whereKey($treatmentPlan->id)->lockForUpdate()->firstOrFail();
            $lockedItem = TreatmentPlanItem::whereKey($item->id)->where('treatment_plan_id', $plan->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($plan->status, ['accepted', 'in_progress'], true) && $lockedItem->status !== 'completed', 409);
            $session = TreatmentSession::create([
                'treatment_plan_item_id' => $lockedItem->id,
                'professional_id' => $request->user()->id,
                'appointment_id' => $data['appointment_id'] ?? null,
                'dental_finding_id' => $dentalFinding?->id,
                'tooth_code' => $data['tooth_code'] ?? null,
                'surfaces' => $data['surfaces'] ?? null,
                'session_at' => $data['session_at'],
                'evolution' => $data['evolution'],
                'status' => 'completed',
            ]);
            if ($request->boolean('complete_item')) {
                $lockedItem->update(['status' => 'completed']);
            }
            if ($plan->status === 'accepted') {
                $plan->update(['status' => 'in_progress']);
            }
            if ($plan->items()->where('status', '!=', 'completed')->doesntExist()) {
                $plan->update(['status' => 'completed']);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'treatment_session.created', 'subject_type' => TreatmentSession::class, 'subject_id' => $session->id, 'after' => ['plan_id' => $plan->id, 'item_id' => $lockedItem->id, 'tooth_code' => $session->tooth_code, 'surfaces' => $session->surfaces, 'dental_finding_id' => $session->dental_finding_id, 'status' => 'completed'], 'ip_address' => $request->ip()]);
        });

        return back()->with('success', 'Sesión clínica registrada en el historial del procedimiento.');
    }
}
