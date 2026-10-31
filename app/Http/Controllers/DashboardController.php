<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canSeeAppointments = $user->can('appointments.view');
        $canSeePatients = $user->can('patients.view');
        $canSeePayments = $user->can('payments.view') || $user->can('reports.financial');
        $canSeeExpenses = $user->can('expenses.view') || $user->can('reports.financial');
        $canSeeCash = $user->can('cash.view') || $user->can('reports.financial');
        $canSeeInventory = $user->can('inventory.view');
        $canSeeFinancialOverview = $user->can('reports.financial');

        $appointments = Appointment::query()
            ->with(['patient:id,patient_code,first_name,last_name', 'professional:id,name'])
            ->whereDate('starts_at', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->limit(8)
            ->when(! $canSeeAppointments, fn ($query) => $query->whereRaw('1 = 0'))
            ->get();
        $pendingConfirmations = Appointment::query()
            ->with(['patient:id,patient_code,first_name,last_name', 'professional:id,name'])
            ->where('status', 'scheduled')
            ->whereBetween('starts_at', [now(), now()->addHours(48)])
            ->when($user->hasRole('dentist'), fn ($query) => $query->where('professional_id', $user->id))
            ->when(! $canSeeAppointments, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('starts_at')->limit(10)->get();
        $internalReminders = AppointmentReminder::query()->with(['appointment.patient:id,patient_code,first_name,last_name', 'appointment.professional:id,name'])
            ->where('status', 'pending')->whereHas('appointment', fn ($query) => $query->where('starts_at', '>', now())->whereIn('status', ['scheduled', 'confirmed']))
            ->when($user->hasRole('dentist'), fn ($query) => $query->whereHas('appointment', fn ($appointments) => $appointments->where('professional_id', $user->id)))
            ->when(! $canSeeAppointments, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('scheduled_for')->limit(10)->get();
        $openSessions = $canSeeCash
            ? CashSession::where('status', 'open')->when(! $user->can('reports.financial'), fn ($query) => $query->where('opened_by', $user->id))->withSum(['movements as posted_income' => fn ($query) => $query->where('status', 'posted')->where('type', 'income')], 'amount')->withSum(['movements as posted_expenses' => fn ($query) => $query->where('status', 'posted')->where('type', 'expense')], 'amount')->get()
            : collect();
        $cashBalance = $canSeeCash ? $openSessions->sum(fn ($session) => (float) $session->opening_balance + (float) $session->posted_income - (float) $session->posted_expenses) : null;
        $stockLowCount = $canSeeInventory
            ? InventoryItem::query()->select('inventory_items.*')->selectSub(
                DB::table('inventory_movements')->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0)")
                    ->whereColumn('inventory_movements.inventory_item_id', 'inventory_items.id'),
                'stock'
            )->where('is_active', true)->get()->filter(fn ($item): bool => (float) $item->stock <= (float) $item->reorder_level)->count()
            : null;
        $batchAlerts = $canSeeInventory
            ? InventoryBatch::query()->select('inventory_batches.id', 'inventory_batches.expires_on')->selectSub(
                DB::table('inventory_movements')->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END), 0)")->whereColumn('inventory_batch_id', 'inventory_batches.id'),
                'stock'
            )->whereNotNull('expires_on')->whereDate('expires_on', '<=', today()->addDays(30))
                ->whereHas('item', fn ($query) => $query->where('is_active', true))->get()->filter(fn ($batch): bool => (float) $batch->stock > 0)
            : collect();
        $expiredBatchCount = $batchAlerts->filter(fn ($batch): bool => $batch->expires_on->lt(today()))->count();
        $expiringBatchCount = $batchAlerts->filter(fn ($batch): bool => $batch->expires_on->gte(today()))->count();
        $paymentsToday = Payment::query()->where('status', 'paid')->whereDate('paid_at', today());
        $paymentsMonth = Payment::query()->where('status', 'paid')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $expensesToday = CashMovement::query()->where('status', 'posted')->where('type', 'expense')->whereDate('created_at', today());
        $expensesMonth = CashMovement::query()->where('status', 'posted')->where('type', 'expense')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        if (! $canSeeFinancialOverview) {
            $paymentsToday->where('received_by', $user->id);
            $paymentsMonth->where('received_by', $user->id);
            $expensesToday->where('recorded_by', $user->id);
            $expensesMonth->where('recorded_by', $user->id);
        }
        $receivables = null;
        $payables = null;
        if ($canSeeFinancialOverview) {
            $asOf = now()->endOfDay();
            $receivables = DB::table('treatment_plans as plans')->whereIn('status', ['accepted', 'in_progress', 'completed'])->whereNotNull('accepted_at')->where('accepted_at', '<=', $asOf)
                ->select('plans.id')
                ->selectSub(DB::table('treatment_plan_items')->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')->whereColumn('treatment_plan_id', 'plans.id'), 'planned')
                ->selectSub(DB::table('payments')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('treatment_plan_id', 'plans.id')->where('status', 'paid')->where('paid_at', '<=', $asOf), 'paid')
                ->get()->sum(fn ($plan): float => max(0, (float) $plan->planned - (float) $plan->paid));
            $payables = DB::table('purchase_orders as orders')->where('orders.status', 'received')->where('orders.received_at', '<=', $asOf)
                ->select('orders.id')
                ->selectSub(DB::table('purchase_order_items')->selectRaw('COALESCE(SUM(quantity * unit_cost), 0)')->whereColumn('purchase_order_id', 'orders.id'), 'ordered')
                ->selectSub(DB::table('supplier_payments')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('purchase_order_id', 'orders.id')->where('status', 'paid')->where('paid_at', '<=', $asOf), 'paid')
                ->get()->sum(fn ($order): float => max(0, (float) $order->ordered - (float) $order->paid));
        }

        return view('dashboard', [
            'appointments' => $appointments,
            'pendingConfirmations' => $pendingConfirmations,
            'internalReminders' => $internalReminders,
            'appointmentCount' => $canSeeAppointments ? Appointment::whereDate('starts_at', today())->where('status', '!=', 'cancelled')->count() : null,
            'patientCount' => $canSeePatients ? Patient::count() : null,
            'incomeToday' => $canSeePayments ? $paymentsToday->sum('amount') : null,
            'incomeMonth' => $canSeePayments ? $paymentsMonth->sum('amount') : null,
            'expensesToday' => $canSeeExpenses ? $expensesToday->sum('amount') : null,
            'expensesMonth' => $canSeeExpenses ? $expensesMonth->sum('amount') : null,
            'receivables' => $receivables,
            'payables' => $payables,
            'activeTreatmentCount' => $user->can('treatments.view') ? TreatmentPlan::whereIn('status', ['accepted', 'in_progress'])->count() : null,
            'pendingTreatmentCount' => $user->can('treatments.view') ? TreatmentPlan::whereIn('status', ['proposed', 'pending_acceptance'])->count() : null,
            'unconfirmedCount' => $canSeeAppointments ? Appointment::where('status', 'scheduled')->whereBetween('starts_at', [now(), now()->addHours(48)])->when($user->hasRole('dentist'), fn ($query) => $query->where('professional_id', $user->id))->count() : null,
            'cashBalance' => $cashBalance,
            'stockLowCount' => $stockLowCount,
            'expiredBatchCount' => $canSeeInventory ? $expiredBatchCount : null,
            'expiringBatchCount' => $canSeeInventory ? $expiringBatchCount : null,
        ]);
    }
}
