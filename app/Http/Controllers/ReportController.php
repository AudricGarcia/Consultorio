<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function financial(Request $request): View
    {
        $this->authorize('reports.financial');
        $from = Carbon::parse($request->date('from') ?? today()->startOfMonth())->startOfDay();
        $to = Carbon::parse($request->date('to') ?? today())->endOfDay();
        abort_if($from->gt($to) || $from->diffInDays($to) > 366, 422, 'El rango debe ser válido y no superar un año.');

        $payments = Payment::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
        $expenses = CashMovement::query()->where('status', 'posted')->where('type', 'expense')->whereBetween('created_at', [$from, $to]);
        $asOf = $to->copy()->endOfDay();
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

        return view('reports.financial', [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'income' => (clone $payments)->sum('amount'),
            'paymentCount' => (clone $payments)->count(),
            'expenses' => (clone $expenses)->sum('amount'),
            'receivables' => $receivables,
            'payables' => $payables,
            'expenseCount' => (clone $expenses)->count(),
            'byMethod' => (clone $payments)->selectRaw('payment_method, COUNT(*) as transactions, SUM(amount) as total')->groupBy('payment_method')->orderBy('payment_method')->get(),
        ]);
    }

    public function audit(Request $request): View
    {
        $this->authorize('audit.view');
        $logs = AuditLog::query()->with('user:id,name')->when($request->filled('event'), fn ($query) => $query->where('event', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('event')->toString()).'%'))
            ->orderByDesc('created_at')->paginate(30)->withQueryString();

        return view('reports.audit', compact('logs'));
    }
}
