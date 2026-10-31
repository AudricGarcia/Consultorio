<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('cash.view') || $request->user()->can('reports.financial'), 403);
        $sessions = CashSession::with(['opener:id,name', 'closer:id,name'])
            ->when(! $request->user()->can('reports.financial'), fn ($query) => $query->where('opened_by', $request->user()->id))
            ->orderByDesc('opened_at')->paginate(20);
        $currentSession = CashSession::where('opened_by', $request->user()->id)->where('status', 'open')->latest('opened_at')->first();
        $pendingExpenses = $request->user()->can('expenses.approve')
            ? CashMovement::with(['session.opener:id,name', 'recorder:id,name'])->where('type', 'expense')->where('status', 'pending_approval')->latest()->get()
            : collect();
        $recentMovements = $currentSession?->movements()->latest()->limit(20)->get() ?? collect();
        $sessionSummary = $currentSession === null ? null : [
            'opening' => (float) $currentSession->opening_balance,
            'income' => (float) $currentSession->movements()->where('status', 'posted')->where('type', 'income')->sum('amount'),
            'expenses' => (float) $currentSession->movements()->where('status', 'posted')->where('type', 'expense')->sum('amount'),
        ];
        if ($sessionSummary !== null) {
            $sessionSummary['expected'] = $sessionSummary['opening'] + $sessionSummary['income'] - $sessionSummary['expenses'];
        }

        return view('cash.index', compact('sessions', 'currentSession', 'pendingExpenses', 'recentMovements', 'sessionSummary'));
    }

    public function open(Request $request): View
    {
        $this->authorize('cash.register');

        return view('cash.open');
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $this->authorize('cash.register');
        $data = $request->validate(['opening_balance' => ['required', 'numeric', 'min:0', 'max:100000000']]);

        $session = DB::transaction(function () use ($request, $data): CashSession {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if (CashSession::where('opened_by', $request->user()->id)->where('status', 'open')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['opening_balance' => 'Ya tienes una caja abierta.']);
            }

            $session = CashSession::create([
                'opened_by' => $request->user()->id,
                'opened_at' => now(),
                'opening_balance' => $data['opening_balance'],
                'status' => 'open',
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'cash_session.opened',
                'subject_type' => CashSession::class,
                'subject_id' => $session->id,
                'after' => ['opening_balance' => $session->opening_balance],
                'ip_address' => $request->ip(),
            ]);

            return $session;
        });

        return redirect()->route('cash.index')->with('success', 'Caja abierta.');
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorize('cash.close');
        abort_unless($cashSession->status === 'open', 409);
        abort_unless($cashSession->opened_by === $request->user()->id || $request->user()->can('reports.financial'), 403);
        $data = $request->validate([
            'counted_balance' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'closing_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $cashSession, $data): void {
            $session = CashSession::whereKey($cashSession->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'open', 409);
            $pending = $session->movements()->where('status', 'pending_approval')->exists();

            if ($pending) {
                throw ValidationException::withMessages(['counted_balance' => 'Resuelve o rechaza los egresos pendientes antes de cerrar caja.']);
            }

            $expected = (float) $session->opening_balance
                + (float) $session->movements()->where('status', 'posted')->where('type', 'income')->sum('amount')
                - (float) $session->movements()->where('status', 'posted')->where('type', 'expense')->sum('amount');
            $difference = round((float) $data['counted_balance'] - $expected, 2);

            if ($difference !== 0.0 && blank($data['closing_note'] ?? null)) {
                throw ValidationException::withMessages(['closing_note' => 'Explica la diferencia entre el saldo teórico y el arqueo.']);
            }

            $session->update([
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
                'counted_balance' => $data['counted_balance'],
                'closing_note' => $data['closing_note'] ?? null,
                'status' => 'closed',
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'cash_session.closed',
                'subject_type' => CashSession::class,
                'subject_id' => $session->id,
                'after' => ['expected_balance' => $expected, 'counted_balance' => $data['counted_balance'], 'difference' => $difference],
                'reason' => $data['closing_note'] ?? null,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('cash.index')->with('success', 'Caja cerrada con arqueo registrado.');
    }

    public function reopen(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorize('cash.reopen');
        abort_unless($cashSession->status === 'closed', 409);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);

        DB::transaction(function () use ($request, $cashSession, $data): void {
            $session = CashSession::whereKey($cashSession->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'closed', 409);
            if (CashSession::where('opened_by', $session->opened_by)->where('status', 'open')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['reason' => 'El responsable ya tiene una caja abierta.']);
            }

            $before = ['status' => $session->status, 'counted_balance' => $session->counted_balance, 'closed_at' => $session->closed_at];
            $session->update(['status' => 'open', 'closed_by' => null, 'closed_at' => null, 'counted_balance' => null, 'closing_note' => null]);
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'cash_session.reopened', 'subject_type' => CashSession::class, 'subject_id' => $session->id, 'before' => $before, 'after' => ['status' => 'open'], 'reason' => $data['reason'], 'ip_address' => $request->ip()]);
        });

        return back()->with('success', 'Caja reabierta con motivo auditado.');
    }

    public function expense(Request $request): RedirectResponse
    {
        $this->authorize('expenses.create');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'category' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:6', 'max:2000'],
        ]);
        $session = CashSession::where('opened_by', $request->user()->id)->where('status', 'open')->latest('opened_at')->firstOrFail();
        $requiresApproval = (float) $data['amount'] >= config('clinic.expense_approval_limit') && ! $request->user()->isOwner();
        $movement = CashMovement::create([
            'cash_session_id' => $session->id,
            'recorded_by' => $request->user()->id,
            'type' => 'expense',
            ...$data,
            'status' => $requiresApproval ? 'pending_approval' : 'posted',
        ]);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => $requiresApproval ? 'expense.pending_approval' : 'expense.created',
            'subject_type' => CashMovement::class,
            'subject_id' => $movement->id,
            'after' => ['amount' => $movement->amount, 'category' => $movement->category, 'status' => $movement->status],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', $requiresApproval ? 'Egreso registrado y pendiente de aprobación.' : 'Egreso registrado en caja.');
    }

    public function approveExpense(Request $request, CashMovement $cashMovement): RedirectResponse
    {
        $this->authorize('expenses.approve');
        abort_unless($cashMovement->type === 'expense' && $cashMovement->status === 'pending_approval', 409);
        abort_if($cashMovement->recorded_by === $request->user()->id, 403, 'Quien registra un egreso pendiente no puede aprobarlo.');

        DB::transaction(function () use ($request, $cashMovement): void {
            $movement = CashMovement::whereKey($cashMovement->id)->lockForUpdate()->firstOrFail();
            $session = CashSession::whereKey($movement->cash_session_id)->lockForUpdate()->firstOrFail();

            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['expense' => 'La caja asociada ya está cerrada; solicita revisión del propietario.']);
            }

            if ($movement->status !== 'pending_approval') {
                throw ValidationException::withMessages(['expense' => 'El egreso ya fue procesado.']);
            }

            $movement->update(['status' => 'posted', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            if ($movement->supplier_payment_id) {
                SupplierPayment::whereKey($movement->supplier_payment_id)->where('status', 'pending_approval')->update(['status' => 'paid', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'paid_at' => now()]);
            }
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'expense.approved',
                'subject_type' => CashMovement::class,
                'subject_id' => $movement->id,
                'after' => ['status' => 'posted', 'amount' => $movement->amount],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Egreso aprobado y contabilizado en caja.');
    }

    public function rejectExpense(Request $request, CashMovement $cashMovement): RedirectResponse
    {
        $this->authorize('expenses.approve');
        abort_unless($cashMovement->type === 'expense' && $cashMovement->status === 'pending_approval', 409);
        abort_if($cashMovement->recorded_by === $request->user()->id, 403, 'Quien registra un egreso pendiente no puede rechazarlo.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);

        DB::transaction(function () use ($request, $cashMovement, $data): void {
            $movement = CashMovement::whereKey($cashMovement->id)->lockForUpdate()->firstOrFail();
            abort_unless($movement->status === 'pending_approval', 409);
            $movement->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'reason' => $movement->reason."\nRechazado: ".$data['reason']]);
            if ($movement->supplier_payment_id) {
                SupplierPayment::whereKey($movement->supplier_payment_id)->where('status', 'pending_approval')->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'expense.rejected', 'subject_type' => CashMovement::class, 'subject_id' => $movement->id, 'after' => ['status' => 'rejected'], 'reason' => $data['reason'], 'ip_address' => $request->ip()]);
        });

        return back()->with('success', 'Egreso rechazado y registrado en auditoría.');
    }
}
