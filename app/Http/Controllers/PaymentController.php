<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $this->authorize('payments.view');
        $payments = Payment::with(['patient', 'receiver:id,name'])->orderByDesc('paid_at')->paginate(30);

        return view('payments.index', compact('payments'));
    }

    public function patientStatement(Request $request, Patient $patient): View
    {
        $this->authorize('payments.view');
        $this->authorize('paymentView', $patient);
        $plans = TreatmentPlan::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['accepted', 'in_progress', 'completed'])
            ->whereNotNull('accepted_at')
            ->when($request->user()->hasRole('dentist') && ! $request->user()->isOwner(), fn ($query) => $query->where('professional_id', $request->user()->id))
            ->with(['installments.allocations.payment:id,status'])
            ->select('id', 'status', 'accepted_at')
            ->selectSub(DB::table('treatment_plan_items')->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')->whereColumn('treatment_plan_id', 'treatment_plans.id'), 'total')
            ->selectSub(DB::table('payments')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('treatment_plan_id', 'treatment_plans.id')->where('status', 'paid'), 'paid')
            ->orderByDesc('accepted_at')->get();
        $planStatements = $plans->map(fn (TreatmentPlan $plan): array => [
            'id' => $plan->id,
            'status' => $plan->status,
            'accepted_at' => $plan->accepted_at,
            'total' => (float) $plan->total,
            'paid' => (float) $plan->paid,
            'balance' => max(0, round((float) $plan->total - (float) $plan->paid, 2)),
            'installments' => $plan->installments->map(function (TreatmentPlanInstallment $installment): array {
                $paid = $installment->allocations->filter(fn ($allocation): bool => $allocation->payment?->status === 'paid')->sum(fn ($allocation): float => (float) $allocation->amount);

                return [
                    'number' => $installment->installment_number,
                    'due_at' => $installment->due_at,
                    'amount' => (float) $installment->amount,
                    'paid' => round($paid, 2),
                    'balance' => max(0, round((float) $installment->amount - $paid, 2)),
                ];
            })->all(),
        ]);
        $patientPayments = Payment::query()->where('patient_id', $patient->id);
        $payments = (clone $patientPayments)->with('treatmentPlan:id')->latest('paid_at')->limit(100)->get(['id', 'patient_id', 'treatment_plan_id', 'amount', 'currency', 'payment_method', 'reference', 'status', 'paid_at']);
        $totals = [
            'receivable' => $planStatements->sum('balance'),
            'paid' => (float) (clone $patientPayments)->where('status', 'paid')->sum('amount'),
            'advances' => (float) (clone $patientPayments)->where('status', 'paid')->whereNull('treatment_plan_id')->sum('amount'),
            'refunded' => (float) (clone $patientPayments)->where('status', 'refunded')->sum('amount'),
        ];

        return view('payments.patient-statement', compact('patient', 'planStatements', 'payments', 'totals'));
    }

    public function searchPatients(Request $request): View
    {
        $this->authorize('payments.create');
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = trim($data['q']);
        $patients = Patient::query()
            ->when($request->user()->hasPermission('clinical.view') && ! $request->user()->isOwner(), fn ($query) => $query->where(function ($scope) use ($request): void {
                $scope->where('created_by', $request->user()->id)
                    ->orWhereHas('appointments', fn ($appointments) => $appointments->where('professional_id', $request->user()->id))
                    ->orWhereHas('clinicalRecords', fn ($records) => $records->where('professional_id', $request->user()->id));
            }))
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', '%'.$term.'%')
                    ->orWhere('last_name', 'like', '%'.$term.'%')
                    ->orWhere('patient_code', 'like', '%'.$term.'%');
            })
            ->orderBy('last_name')->orderBy('first_name')->limit(20)->get(['id', 'patient_code', 'first_name', 'last_name', 'created_by']);

        return view('payments.patient-search', compact('patients', 'term'));
    }

    public function create(Patient $patient): View
    {
        $this->authorize('payments.create');
        $this->authorize('paymentView', $patient);
        $plans = TreatmentPlan::where('patient_id', $patient->id)
            ->whereIn('status', ['accepted', 'in_progress', 'completed'])
            ->whereNotNull('accepted_at')
            ->with(['items:id,treatment_plan_id,quantity,unit_price', 'payments:id,treatment_plan_id,amount,status', 'installments'])
            ->orderByDesc('created_at')->get()
            ->map(function (TreatmentPlan $plan): TreatmentPlan {
                $total = $plan->items->sum(fn ($item): float => (float) $item->quantity * (float) $item->unit_price);
                $paid = $plan->payments->where('status', 'paid')->sum(fn ($payment): float => (float) $payment->amount);
                $plan->setAttribute('outstanding_balance', max(0, round($total - $paid, 2)));

                return $plan;
            })
            ->filter(fn (TreatmentPlan $plan): bool => (float) $plan->outstanding_balance > 0)
            ->values();
        $cashSession = CashSession::where('opened_by', auth()->id())->where('status', 'open')->latest('opened_at')->first();

        return view('payments.create', compact('patient', 'plans', 'cashSession'));
    }

    public function store(StorePaymentRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('paymentView', $patient);
        $data = $request->validated();

        if ((int) $data['patient_id'] !== $patient->id) {
            abort(404);
        }

        $session = CashSession::where('opened_by', $request->user()->id)->where('status', 'open')->latest('opened_at')->first();

        if ($session === null) {
            return redirect()->route('cash.index')->withErrors(['cash' => 'Abre tu caja antes de registrar un cobro.']);
        }

        $payment = DB::transaction(function () use ($data, $request, $session, $patient): Payment {
            $lockedSession = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status !== 'open') {
                throw ValidationException::withMessages(['cash' => 'La caja ya está cerrada.']);
            }

            $plan = null;
            if (isset($data['treatment_plan_id'])) {
                $plan = TreatmentPlan::whereKey($data['treatment_plan_id'])->where('patient_id', $patient->id)->lockForUpdate()->with('items')->first();
                if (! $plan || ! in_array($plan->status, ['accepted', 'in_progress', 'completed'], true) || $plan->accepted_at === null) {
                    throw ValidationException::withMessages(['treatment_plan_id' => 'El plan no pertenece a este paciente o aún no está aceptado.']);
                }
                $planned = $plan->items->sum(fn ($item): float => (float) $item->quantity * (float) $item->unit_price);
                $paid = (float) Payment::where('treatment_plan_id', $plan->id)->where('status', 'paid')->sum('amount');
                if ((float) $data['amount'] > max(0, round($planned - $paid, 2))) {
                    throw ValidationException::withMessages(['amount' => 'El pago supera el saldo del plan. Registra el excedente como anticipo sin asociar.']);
                }
            }

            $payment = Payment::create([
                ...$data,
                'received_by' => $request->user()->id,
                'currency' => config('clinic.currency', 'PEN'),
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            if ($plan !== null) {
                $this->allocateToInstallments($payment, $plan);
            }
            CashMovement::create([
                'cash_session_id' => $lockedSession->id,
                'payment_id' => $payment->id,
                'recorded_by' => $request->user()->id,
                'type' => 'income',
                'amount' => $payment->amount,
                'category' => 'patient_payment',
                'reference' => $payment->reference,
                'reason' => 'Pago de paciente '.$patient->patient_code,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'payment.created',
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'after' => ['patient_id' => $payment->patient_id, 'amount' => $payment->amount, 'method' => $payment->payment_method],
                'ip_address' => $request->ip(),
            ]);

            return $payment;
        });

        return redirect()->route('payments.index')->with('success', 'Pago registrado y asociado a la caja abierta.');
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('payments.refund');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $session = CashSession::where('opened_by', $request->user()->id)->where('status', 'open')->latest('opened_at')->firstOrFail();

        DB::transaction(function () use ($request, $payment, $data, $session): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'paid') {
                throw ValidationException::withMessages(['payment' => 'Este pago ya fue revertido.']);
            }

            $lockedSession = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status !== 'open') {
                throw ValidationException::withMessages(['cash' => 'La caja está cerrada.']);
            }

            $payment->update(['status' => 'refunded', 'reversed_by' => $request->user()->id, 'reversal_reason' => $data['reason']]);
            CashMovement::create([
                'cash_session_id' => $lockedSession->id,
                'recorded_by' => $request->user()->id,
                'type' => 'expense',
                'amount' => $payment->amount,
                'category' => 'payment_refund',
                'reference' => 'PAY-'.$payment->id,
                'reason' => $data['reason'],
                'status' => 'posted',
                'reversal_of' => CashMovement::where('payment_id', $payment->id)->value('id'),
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'payment.refunded',
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'after' => ['amount' => $payment->amount, 'reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Devolución autorizada y registrada como egreso de caja.');
    }

    private function allocateToInstallments(Payment $payment, TreatmentPlan $plan): void
    {
        $installments = $plan->installments()->lockForUpdate()->get();
        if ($installments->isEmpty()) {
            $totalCents = (int) round($plan->items->sum(fn ($item): float => (float) $item->quantity * (float) $item->unit_price) * 100);
            if ($totalCents > 0) {
                $installments = collect([$plan->installments()->create([
                    'installment_number' => 1,
                    'due_at' => today()->toDateString(),
                    'amount' => number_format($totalCents / 100, 2, '.', ''),
                ])]);
            }
        }

        $remainingCents = (int) round((float) $payment->amount * 100);
        foreach ($installments as $installment) {
            $paidCents = (int) round((float) DB::table('payment_allocations')->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->where('payment_allocations.treatment_plan_installment_id', $installment->id)->where('payments.status', 'paid')->sum('payment_allocations.amount') * 100);
            $balanceCents = max(0, (int) round((float) $installment->amount * 100) - $paidCents);
            $allocatedCents = min($remainingCents, $balanceCents);
            if ($allocatedCents < 1) {
                continue;
            }

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'treatment_plan_installment_id' => $installment->id,
                'amount' => number_format($allocatedCents / 100, 2, '.', ''),
            ]);
            $remainingCents -= $allocatedCents;
            if ($remainingCents < 1) {
                break;
            }
        }

        if ($remainingCents > 0) {
            throw ValidationException::withMessages(['amount' => 'No fue posible distribuir el pago en las cuotas pendientes. Revisa el saldo y vuelve a intentarlo.']);
        }
    }
}
